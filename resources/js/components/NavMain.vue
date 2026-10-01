<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { NavGroup, NavItem } from '@/types';

const props = defineProps<{
    groups: NavGroup[];
}>();

const { currentUrl, isCurrentUrl } = useCurrentUrl();
const { state, isMobile } = useSidebar();

const collapsed = computed(
    () => state.value === 'collapsed' && !isMobile.value,
);

/**
 * The link that best matches the current page: the longest link URL that the
 * current URL starts with. `/employees/12/edit` therefore lights up
 * "Active Employees", while `/employees/past` lights up "Past Employees".
 */
const activeUrl = computed(() => {
    const items = props.groups.flatMap((group) => group.items ?? []);

    // A page that lives under another section's URL names its own link:
    // `/employees/12/attendance` belongs to "Attendance Calendar".
    const claimed = items.find((item) => item.matches?.test(currentUrl.value));

    if (claimed) {
        return toUrl(claimed.href);
    }

    const urls = props.groups.flatMap((group) => [
        ...(group.href ? [toUrl(group.href)] : []),
        ...(group.items ?? []).map((item) => toUrl(item.href)),
    ]);

    return urls
        .filter(
            (url) =>
                currentUrl.value === url ||
                currentUrl.value.startsWith(`${url}/`),
        )
        .sort((a, b) => b.length - a.length)[0];
});

const isActive = (item: NavItem): boolean =>
    toUrl(item.href) === activeUrl.value;

const groupIsActive = (group: NavGroup): boolean =>
    (group.items ?? []).some(isActive);
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarMenu>
            <template v-for="group in groups" :key="group.title">
                <!-- A single destination -->
                <SidebarMenuItem v-if="group.href">
                    <SidebarMenuButton
                        as-child
                        :is-active="
                            toUrl(group.href) === activeUrl ||
                            isCurrentUrl(group.href)
                        "
                        :tooltip="group.title"
                    >
                        <Link :href="group.href">
                            <component :is="group.icon" />
                            <span>{{ group.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>

                <!-- With the sidebar collapsed to icons, a section goes straight to its first page -->
                <SidebarMenuItem v-else-if="collapsed && group.items?.length">
                    <SidebarMenuButton
                        as-child
                        :is-active="groupIsActive(group)"
                        :tooltip="group.title"
                    >
                        <Link :href="group.items[0].href">
                            <component :is="group.icon" />
                            <span>{{ group.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>

                <!-- A section that expands to its pages -->
                <Collapsible
                    v-else-if="group.items?.length"
                    as-child
                    :default-open="groupIsActive(group)"
                    class="group/collapsible"
                >
                    <SidebarMenuItem>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton :tooltip="group.title">
                                <component :is="group.icon" />
                                <span>{{ group.title }}</span>
                                <ChevronRight
                                    class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem
                                    v-for="item in group.items"
                                    :key="item.title"
                                >
                                    <SidebarMenuSubButton
                                        as-child
                                        :is-active="isActive(item)"
                                    >
                                        <Link :href="item.href">
                                            <span>{{ item.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
