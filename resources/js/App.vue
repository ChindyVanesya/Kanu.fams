<script setup>
import AttendancePage from './pages/AttendancePage.vue';
import AdminPage from './pages/AdminPage.vue';
import LoginPage from './pages/LoginPage.vue';
import FeedbackHost from './components/FeedbackHost.vue';

const page = document.body.dataset.page;
const pageComponent = page === 'admin'
    ? AdminPage
    : page === 'login'
        ? LoginPage
        : AttendancePage;
</script>

<template>
    <div>
        <component :is="pageComponent" />
        <FeedbackHost />
    </div>
</template>
