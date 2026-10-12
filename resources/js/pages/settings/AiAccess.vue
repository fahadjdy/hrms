<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { Check, Copy, KeyRound, Plug, TriangleAlert } from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import McpAccessController from '@/actions/App/Http/Controllers/Settings/McpAccessController';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import EmptyState from '@/components/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFormat } from '@/composables/useFormat';
import { edit } from '@/routes/mcp-access';

type AccessToken = {
    id: string;
    label: string;
    kind: 'personal' | 'oauth';
    client_name: string | null;
    scopes: string[];
    created_at: string | null;
    expires_at: string | null;
};

type NewToken = {
    label: string;
    token: string;
    command: string;
};

const props = defineProps<{
    mcpUrl: string;
    tokens: AccessToken[];
    claudeClientId: string;
    claudeCodeCommand: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'AI access',
                href: edit(),
            },
        ],
    },
});

const { date } = useFormat();
const page = usePage();

/**
 * The token flashed right after it was created. Kept while this page stays
 * open, so it is still visible after revoking another token; it is never sent
 * by the server again.
 */
const newToken = ref<NewToken | null>(null);

watch(
    () => (page.flash as { mcpToken?: NewToken }).mcpToken,
    (value) => {
        if (value) {
            newToken.value = value;
        }
    },
    { immediate: true },
);

const { copy, isSupported } = useClipboard({ legacy: true });

/** Which value was copied last, so only its button shows the tick. */
const copiedKey = ref<string | null>(null);
let resetTimer: ReturnType<typeof setTimeout> | undefined;

async function copyValue(key: string, value: string): Promise<void> {
    await copy(value);
    copiedKey.value = key;
    clearTimeout(resetTimer);
    resetTimer = setTimeout(() => (copiedKey.value = null), 2000);
}

/* Revoking a token or a connected app. */
const revoking = ref<AccessToken | null>(null);
const confirmOpen = ref(false);
const revokeProcessing = ref(false);

const revokeDescription = computed(
    () =>
        `"${revoking.value?.label ?? ''}" will stop working immediately. Anything using it must be connected again.`,
);

function askRevoke(token: AccessToken): void {
    revoking.value = token;
    confirmOpen.value = true;
}

function revoke(): void {
    if (!revoking.value) {
        return;
    }

    router.delete(McpAccessController.destroy.url(revoking.value.id), {
        preserveScroll: true,
        onStart: () => (revokeProcessing.value = true),
        onFinish: () => {
            revokeProcessing.value = false;
            confirmOpen.value = false;
        },
    });
}

const connectorSteps = [
    'Open Settings → Connectors and choose "Add custom connector".',
    'Give it a name (for example HRMS) and paste the MCP URL above.',
    'Choose Connect, sign in to HRMS in the window that opens and choose Authorize.',
];
</script>

<template>
    <Head title="AI access" />

    <h1 class="sr-only">AI access</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="AI access"
            description="Connect Claude, ChatGPT or another AI assistant to HRMS. It works as you, with exactly your permissions."
        />

        <div class="flex flex-col gap-2">
            <Label for="mcp-url">MCP URL</Label>
            <div
                class="flex items-center gap-2 rounded-md border bg-muted/40 py-1 pr-1 pl-3"
            >
                <span
                    id="mcp-url"
                    class="min-w-0 flex-1 truncate font-mono text-sm select-all"
                >
                    {{ props.mcpUrl }}
                </span>
                <Button
                    v-if="isSupported"
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-8 shrink-0"
                    aria-label="Copy MCP URL"
                    @click="copyValue('url', props.mcpUrl)"
                >
                    <Check v-if="copiedKey === 'url'" class="text-positive" />
                    <Copy v-else />
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">
                Use this exact address, ending in <code>/mcp</code>. Any other
                page of HRMS will not work as a connector URL.
            </p>
        </div>
    </div>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Claude.ai, Claude Desktop and ChatGPT"
            description="Add HRMS as a custom connector. You sign in once; no token needs to be copied."
        />

        <ol
            class="flex list-decimal flex-col gap-2 pl-5 text-sm text-muted-foreground"
        >
            <li v-for="step in connectorSteps" :key="step">{{ step }}</li>
        </ol>

        <div class="flex flex-col gap-2">
            <Label for="claude-client-id">OAuth Client ID (Claude)</Label>
            <div
                class="flex items-center gap-2 rounded-md border bg-muted/40 py-1 pr-1 pl-3"
            >
                <span
                    id="claude-client-id"
                    class="min-w-0 flex-1 truncate font-mono text-sm select-all"
                >
                    {{ props.claudeClientId }}
                </span>
                <Button
                    v-if="isSupported"
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-8 shrink-0"
                    aria-label="Copy OAuth Client ID"
                    @click="copyValue('client', props.claudeClientId)"
                >
                    <Check
                        v-if="copiedKey === 'client'"
                        class="text-positive"
                    />
                    <Copy v-else />
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">
                Only needed if Claude cannot register itself: paste it under
                Advanced settings → OAuth Client ID when adding the connector,
                and leave the client secret empty.
            </p>
        </div>
    </div>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Claude Code and other tools"
            description="Create a personal token and paste it into the tool's settings."
        />

        <Alert v-if="newToken" class="border-warning/40 bg-warning-soft">
            <TriangleAlert class="text-warning" />
            <AlertTitle>Copy your token now</AlertTitle>
            <AlertDescription class="flex min-w-0 flex-col gap-3">
                <p>
                    "{{ newToken.label }}" is ready. It will not be shown again
                    after you leave this page.
                </p>

                <div
                    class="flex w-full items-center gap-2 rounded-md border bg-background py-1 pr-1 pl-3"
                >
                    <span
                        class="min-w-0 flex-1 truncate font-mono text-xs text-foreground select-all"
                        data-test="new-token"
                    >
                        {{ newToken.token }}
                    </span>
                    <Button
                        v-if="isSupported"
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8 shrink-0"
                        aria-label="Copy token"
                        @click="copyValue('token', newToken.token)"
                    >
                        <Check
                            v-if="copiedKey === 'token'"
                            class="text-positive"
                        />
                        <Copy v-else />
                    </Button>
                </div>

                <p class="text-foreground">Connect Claude Code with:</p>
                <div
                    class="flex w-full items-start gap-2 rounded-md border bg-background py-1 pr-1 pl-3"
                >
                    <code
                        class="min-w-0 flex-1 py-1.5 font-mono text-xs break-all text-foreground select-all"
                    >
                        {{ newToken.command }}
                    </code>
                    <Button
                        v-if="isSupported"
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="size-8 shrink-0"
                        aria-label="Copy Claude Code command"
                        @click="copyValue('command', newToken.command)"
                    >
                        <Check
                            v-if="copiedKey === 'command'"
                            class="text-positive"
                        />
                        <Copy v-else />
                    </Button>
                </div>
            </AlertDescription>
        </Alert>

        <p v-else class="text-sm text-muted-foreground">
            After creating a token you get a ready command like
            <code class="font-mono text-xs break-all">{{
                props.claudeCodeCommand
            }}</code>
        </p>

        <Form
            v-bind="McpAccessController.store.form()"
            class="flex flex-col gap-2"
            reset-on-success
            :options="{ preserveScroll: true }"
            v-slot="{ errors, processing }"
        >
            <Label for="label">Token name</Label>
            <div class="flex flex-col gap-2 sm:flex-row">
                <Input
                    id="label"
                    name="label"
                    class="sm:flex-1"
                    required
                    maxlength="60"
                    placeholder="e.g. Claude Code on my laptop"
                    autocomplete="off"
                />
                <Button :disabled="processing" data-test="create-token-button">
                    <KeyRound />
                    Create token
                </Button>
            </div>
            <InputError :message="errors.label" />
        </Form>
    </div>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Connected apps and tokens"
            description="Everything that can currently use HRMS as you. Revoke anything you no longer use."
        />

        <div class="rounded-xl border">
            <EmptyState
                v-if="props.tokens.length === 0"
                :icon="Plug"
                title="Nothing connected yet"
                description="Connected assistants and personal tokens appear here."
            />

            <ul v-else class="divide-y">
                <li
                    v-for="token in props.tokens"
                    :key="token.id"
                    class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex min-w-0 flex-col gap-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="truncate text-sm font-medium">
                                {{ token.label }}
                            </span>
                            <StatusBadge
                                :tone="
                                    token.kind === 'oauth' ? 'info' : 'neutral'
                                "
                            >
                                {{
                                    token.kind === 'oauth'
                                        ? 'Connected app'
                                        : 'Personal token'
                                }}
                            </StatusBadge>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Created {{ date(token.created_at) }}
                            <template v-if="token.expires_at">
                                · expires {{ date(token.expires_at) }}
                            </template>
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="w-full sm:w-auto"
                        @click="askRevoke(token)"
                    >
                        Revoke
                    </Button>
                </li>
            </ul>
        </div>
    </div>

    <ConfirmDialog
        v-model:open="confirmOpen"
        title="Revoke access?"
        :description="revokeDescription"
        confirm-label="Revoke"
        destructive
        :processing="revokeProcessing"
        @confirm="revoke"
    />
</template>
