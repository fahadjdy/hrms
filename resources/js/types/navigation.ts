import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    /** Shown only to users who hold this permission. */
    permission?: string;
    /** Page paths that belong to this link although they sit under another URL. */
    matches?: RegExp;
};

/** A sidebar section: a single link, or a group of links that expands. */
export type NavGroup = {
    title: string;
    icon: LucideIcon;
    href?: NonNullable<InertiaLinkProps['href']>;
    permission?: string;
    items?: NavItem[];
};
