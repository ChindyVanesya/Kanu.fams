<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberStatusRequest;
use App\Models\Member;
use App\Models\RegistrationFeePayment;
use App\Services\MemberSpreadsheetParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = mb_strtolower(trim($request->string('search')->value()));

        $members = Member::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']);
            })
            ->with(['registrationFeePayments' => fn ($query) => $query
                ->where('year', now('Asia/Jayapura')->year)
                ->latest('paid_at')])
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $members->getCollection()->each(function (Member $member): void {
            $paid = (int) $member->registrationFeePayments->sum('amount');
            $member->setAttribute('registration_paid', $paid);
            $member->setAttribute('registration_balance', max(0, 100000 - $paid));
            $member->setAttribute('registration_year', now('Asia/Jayapura')->year);
        });

        return response()->json([
            'members' => $members,
        ]);
    }

    public function store(StoreMemberRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $initialPayment = (int) ($validated['registration_payment_amount'] ?? 0);
        unset($validated['registration_payment_amount']);

        $member = DB::transaction(function () use ($request, $validated, $initialPayment): Member {
            $member = Member::query()->create([
                ...$validated,
                'status' => $validated['status'] ?? 'active',
            ]);

            if ($initialPayment > 0) {
                RegistrationFeePayment::query()->create([
                    'member_id' => $member->id,
                    'year' => now('Asia/Jayapura')->year,
                    'amount' => $initialPayment,
                    'paid_at' => now('Asia/Jayapura'),
                    'recorded_by' => $request->user()->id,
                ]);
            }

            return $member;
        });

        return response()->json([
            'message' => 'Anggota berhasil ditambahkan.',
            'member' => $member,
        ], 201);
    }

    public function import(Request $request, MemberSpreadsheetParser $parser): JsonResponse
    {
        $file = $request->validate([
            'file' => ['required', 'file', 'max:2048'],
        ])['file'];
        $extension = mb_strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['xlsx', 'csv'], true)) {
            throw ValidationException::withMessages(['file' => 'Gunakan file Excel .xlsx atau file .csv.']);
        }

        try {
            $rows = $parser->parse($file->getRealPath(), $extension);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['file' => 'File tidak berisi data anggota.']);
        }

        if (count($rows) > 5000) {
            throw ValidationException::withMessages(['file' => 'Maksimal 5.000 anggota dapat diimpor dalam satu file berukuran 2 MB.']);
        }

        $importedCount = DB::transaction(function () use ($rows): int {
            $existingCodes = Member::query()->lockForUpdate()->pluck('member_code')
                ->filter()
                ->mapWithKeys(fn (string $code): array => [mb_strtolower($code) => true])
                ->all();
            $reservedCodes = $existingCodes;
            $members = [];
            $errors = [];

            foreach ($rows as $index => $row) {
                $codeKey = mb_strtolower($row['member_code']);
                $rowNumber = $index + 2;

                if (isset($reservedCodes[$codeKey])) {
                    $errors["baris_{$rowNumber}.kode_anggota"][] = "Kode anggota {$row['member_code']} sudah digunakan.";
                }

                $reservedCodes[$codeKey] = true;
            }

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;
                $validator = Validator::make($row, [
                    'name' => ['required', 'string', 'max:255'],
                    'member_code' => ['required', 'string', 'max:255'],
                    'birth_date' => ['nullable', 'date_format:Y-m-d'],
                    'phone' => ['nullable', 'string', 'max:32'],
                    'has_jersey' => ['required', 'boolean'],
                ], [
                    'name.required' => 'Nama anggota wajib diisi.',
                    'name.max' => 'Nama anggota maksimal 255 karakter.',
                    'member_code.required' => 'Kode anggota wajib diisi.',
                    'member_code.max' => 'Kode anggota maksimal 255 karakter.',
                    'birth_date.date_format' => 'Tanggal lahir harus berformat tanggal yang valid.',
                    'phone.max' => 'Nomor telepon maksimal 32 karakter.',
                    'has_jersey.required' => 'Status kepemilikan jersey wajib diisi.',
                    'has_jersey.boolean' => 'Status kepemilikan jersey tidak valid.',
                ]);

                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $message) {
                        $errors["baris_{$rowNumber}"][] = $message;
                    }

                    continue;
                }

                $members[] = [
                    'name' => $row['name'],
                    'member_code' => $row['member_code'],
                    'birth_date' => $row['birth_date'],
                    'phone' => $row['phone'],
                    'has_jersey' => $row['has_jersey'],
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            Member::query()->insert($members);

            return count($members);
        });

        return response()->json([
            'message' => "Berhasil mengimpor {$importedCount} anggota.",
            'imported_count' => $importedCount,
        ], 201);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            echo "\xEF\xBB\xBF";
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Nama Anggota', 'Kode Anggota', 'Tanggal Lahir', 'No Telepon', 'Memiliki Jersey']);
            fclose($output);
        }, 'template-anggota.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function updateStatus(UpdateMemberStatusRequest $request, Member $member): JsonResponse
    {
        $member->update($request->validated());

        return response()->json([
            'message' => 'Status anggota berhasil diperbarui.',
            'member' => $member->refresh(),
        ]);
    }

    public function updateJersey(Request $request, Member $member): JsonResponse
    {
        $validated = $request->validate([
            'has_jersey' => ['required', 'boolean'],
        ]);

        $member->update($validated);

        return response()->json([
            'message' => 'Status jersey anggota berhasil diperbarui.',
            'member' => $member->refresh(),
        ]);
    }
}
