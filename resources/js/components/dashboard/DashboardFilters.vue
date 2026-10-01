<script setup lang="ts">
import { reactive, watch } from 'vue';
import NativeSelect from '@/components/NativeSelect.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { DashboardFilters, DashboardOptions } from '@/types';

const props = defineProps<{
    filters: DashboardFilters;
    options: DashboardOptions;
}>();

const emit = defineEmits<{
    change: [filters: DashboardFilters];
}>();

// A local copy: the range inputs are edited freely and only sent once complete.
const state = reactive<DashboardFilters>({ ...props.filters });

watch(
    () => props.filters,
    (value) => Object.assign(state, value),
);

const presets = [
    { value: 'current_month', label: 'Current month' },
    { value: 'previous_month', label: 'Previous month' },
    { value: 'custom', label: 'Custom date range' },
];

function update(): void {
    if (
        state.preset === 'custom' &&
        (!state.from || !state.to || state.to < state.from)
    ) {
        return;
    }

    emit('change', { ...state });
}
</script>

<template>
    <!-- One filter row above everything it scopes: every widget below reads the same slice. -->
    <form
        class="grid gap-3 rounded-lg border bg-card p-3 sm:grid-cols-2 lg:grid-cols-[repeat(auto-fit,minmax(11rem,1fr))]"
        aria-label="Dashboard filters"
        @submit.prevent="update"
    >
        <div class="grid gap-1">
            <Label for="dash-preset" class="text-xs text-muted-foreground">
                Period
            </Label>
            <NativeSelect
                id="dash-preset"
                v-model="state.preset"
                :options="presets"
                @update:model-value="update"
            />
        </div>

        <template v-if="state.preset === 'custom'">
            <div class="grid gap-1">
                <Label for="dash-from" class="text-xs text-muted-foreground">
                    From
                </Label>
                <Input
                    id="dash-from"
                    v-model="state.from"
                    type="date"
                    :max="state.to"
                    @change="update"
                />
            </div>
            <div class="grid gap-1">
                <Label for="dash-to" class="text-xs text-muted-foreground">
                    To
                </Label>
                <Input
                    id="dash-to"
                    v-model="state.to"
                    type="date"
                    :min="state.from"
                    @change="update"
                />
            </div>
        </template>

        <div class="grid gap-1">
            <Label for="dash-department" class="text-xs text-muted-foreground">
                Department
            </Label>
            <NativeSelect
                id="dash-department"
                v-model="state.department_id"
                :options="
                    options.departments.map((d) => ({
                        value: d.id,
                        label: d.name,
                    }))
                "
                placeholder="All departments"
                @update:model-value="update"
            />
        </div>

        <div class="grid gap-1">
            <Label for="dash-employee" class="text-xs text-muted-foreground">
                Employee
            </Label>
            <NativeSelect
                id="dash-employee"
                v-model="state.employee_id"
                :options="
                    options.employees.map((e) => ({
                        value: e.id,
                        label: e.name,
                    }))
                "
                placeholder="All employees"
                @update:model-value="update"
            />
        </div>

        <div class="grid gap-1">
            <Label for="dash-status" class="text-xs text-muted-foreground">
                Employee status
            </Label>
            <NativeSelect
                id="dash-status"
                v-model="state.employee_status"
                :options="options.statuses"
                placeholder="Any status"
                @update:model-value="update"
            />
        </div>
    </form>
</template>
