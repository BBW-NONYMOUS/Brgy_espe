import { Form, Head, Link } from '@inertiajs/react';
import PasswordChangeController from '@/actions/App/Http/Controllers/Auth/PasswordChangeController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';

type Props = {
    passwordRules: string;
};

export default function ChangePassword({ passwordRules }: Props) {
    return (
        <>
            <Head title="Change your password" />

            <Form
                {...PasswordChangeController.store.form()}
                resetOnError
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="current_password">
                                Temporary password
                            </Label>
                            <PasswordInput
                                id="current_password"
                                name="current_password"
                                autoComplete="current-password"
                                autoFocus
                                required
                            />
                            <InputError message={errors.current_password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">New password</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                                aria-describedby="password-help"
                                required
                            />
                            <p
                                id="password-help"
                                className="text-sm text-muted-foreground"
                            >
                                At least 10 characters with uppercase and
                                lowercase letters and a number.
                            </p>
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirm new password
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                required
                            />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                            data-test="change-password-button"
                        >
                            {processing && <Spinner />}
                            Save new password
                        </Button>

                        <Link
                            href={logout()}
                            as="button"
                            className="text-center text-sm text-muted-foreground underline underline-offset-4"
                        >
                            Log out instead
                        </Link>
                    </div>
                )}
            </Form>
        </>
    );
}

ChangePassword.layout = {
    title: 'Change your password',
    description:
        'For your security, replace the temporary password before using the system.',
};
