<script setup lang="ts">
import { Form, Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import ProfilePhotoController from '@/actions/App/Http/Controllers/Settings/ProfilePhotoController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInitials } from '@/composables/useInitials';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Profile settings',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const { getInitials } = useInitials();

/* Profile photo: uploaded as soon as a file is chosen. */
const photoInput = ref<HTMLInputElement | null>(null);
const photoForm = useForm({ photo: null as File | null });
const removingPhoto = ref(false);

function onPhotoChosen(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    // Lets the same file be picked again after an error.
    input.value = '';

    if (!file) {
        return;
    }

    photoForm.photo = file;
    photoForm.post(ProfilePhotoController.update.url(), {
        preserveScroll: true,
        onFinish: () => photoForm.reset('photo'),
    });
}

function removePhoto(): void {
    photoForm.clearErrors();
    router.delete(ProfilePhotoController.destroy.url(), {
        preserveScroll: true,
        onStart: () => (removingPhoto.value = true),
        onFinish: () => (removingPhoto.value = false),
    });
}
</script>

<template>
    <Head title="Profile settings" />

    <h1 class="sr-only">Profile settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Profile photo"
            description="Shown in the top bar and wherever your name appears"
        />

        <div class="flex flex-wrap items-center gap-4">
            <Avatar class="size-20">
                <AvatarImage
                    v-if="user.avatar"
                    :src="user.avatar"
                    :alt="user.name"
                    class="object-cover"
                />
                <AvatarFallback
                    class="bg-primary text-xl font-semibold text-primary-foreground"
                >
                    {{ getInitials(user.name) }}
                </AvatarFallback>
            </Avatar>

            <div class="flex min-w-0 flex-col gap-2">
                <div class="flex flex-wrap gap-2">
                    <input
                        ref="photoInput"
                        id="profile-photo"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                        tabindex="-1"
                        aria-hidden="true"
                        @change="onPhotoChosen"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="photoForm.processing"
                        data-test="upload-photo-button"
                        @click="photoInput?.click()"
                    >
                        {{
                            photoForm.processing
                                ? 'Uploading…'
                                : user.avatar
                                  ? 'Change photo'
                                  : 'Upload photo'
                        }}
                    </Button>
                    <Button
                        v-if="user.avatar"
                        type="button"
                        variant="ghost"
                        :disabled="removingPhoto || photoForm.processing"
                        data-test="remove-photo-button"
                        @click="removePhoto"
                    >
                        Remove
                    </Button>
                </div>
                <p class="text-xs text-muted-foreground">
                    JPG, PNG or WebP, up to 2 MB.
                </p>
                <InputError :message="photoForm.errors.photo" />
            </div>
        </div>

        <Heading
            variant="small"
            title="Profile"
            description="Update your name and email address"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Full name"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="Email address"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    Your email address is unverified.
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-if="page.props.status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button"
                    >Save</Button
                >
            </div>
        </Form>
    </div>
</template>
