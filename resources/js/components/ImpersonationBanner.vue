<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { UserCog } from '@lucide/vue';
import { leave } from '@/routes/impersonation';

const page = usePage();
</script>

<template>
    <div
        v-if="page.props.auth.impersonating"
        role="status"
        class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-b bg-warning-soft px-4 py-2 text-sm text-warning"
    >
        <p class="flex items-center gap-2">
            <UserCog class="size-4 shrink-0" />
            <span>
                You are signed in as {{ page.props.auth.user.name }}
                <template v-if="page.props.company">
                    at {{ page.props.company.name }}</template
                >. Changes you make are real.
            </span>
        </p>
        <Link
            :href="leave()"
            method="post"
            as="button"
            class="font-medium underline underline-offset-4"
        >
            Return to platform admin
        </Link>
    </div>
</template>
