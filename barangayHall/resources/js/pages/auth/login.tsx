import { Form, Head } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';

type Props = {
    status?: string;
    healthCenter: {
        name: string;
        barangay: string;
        municipality: string;
        province: string;
    };
};

export default function Login({ status, healthCenter }: Props) {
    return (
        <>
            <Head title="Log in" />

            {status && (
                <Alert>
                    <AlertDescription>{status}</AlertDescription>
                </Alert>
            )}

            <PasskeyVerify separator="Or log in with your username" />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="username">Username</Label>
                            <Input
                                id="username"
                                name="username"
                                required
                                autoFocus
                                autoComplete="username"
                                autoCapitalize="none"
                                spellCheck={false}
                                placeholder="e.g. bhw.analiza"
                                className="h-11"
                                aria-invalid={
                                    errors.username ? true : undefined
                                }
                                aria-describedby={
                                    errors.username
                                        ? 'username-error'
                                        : undefined
                                }
                            />
                            <InputError
                                id="username-error"
                                message={errors.username}
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">Password</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="current-password"
                                className="h-11"
                                aria-invalid={
                                    errors.password ? true : undefined
                                }
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center gap-3">
                            <Checkbox
                                id="remember"
                                name="remember"
                                className="size-5"
                            />
                            <Label htmlFor="remember" className="font-normal">
                                Keep me logged in on this computer
                            </Label>
                        </div>

                        <Button
                            type="submit"
                            size="lg"
                            className="h-12 w-full text-base"
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            Log in
                        </Button>

                        <p className="text-center text-sm text-muted-foreground">
                            Forgot your password? Ask the health center
                            administrator to reset it.
                        </p>
                    </>
                )}
            </Form>

            <Alert className="border-info/30 bg-info-soft text-info-soft-foreground">
                <ShieldCheck aria-hidden />
                <AlertTitle>Data Privacy Notice</AlertTitle>
                <AlertDescription className="text-info-soft-foreground">
                    <p>
                        This system holds personal and health information of
                        residents of Brgy. {healthCenter.barangay}. Use it only
                        for health center work. Every access is recorded.
                    </p>
                    <PrivacyNotice healthCenter={healthCenter} />
                </AlertDescription>
            </Alert>
        </>
    );
}

function PrivacyNotice({ healthCenter }: Pick<Props, 'healthCenter'>) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    variant="link"
                    className="h-auto p-0 font-bold text-info-soft-foreground underline"
                >
                    Read the full privacy notice
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[85dvh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Data Privacy Notice</DialogTitle>
                    <DialogDescription>
                        {healthCenter.name}, Barangay {healthCenter.barangay},{' '}
                        {healthCenter.municipality}, {healthCenter.province}
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-3 text-sm leading-relaxed">
                    <p>
                        <strong>What we collect.</strong> Residents&apos; names,
                        birthdates, household and contact details, PhilHealth
                        number, and health information such as consultations,
                        vital signs, immunizations, conditions and maternal care
                        records.
                    </p>
                    <p>
                        <strong>Why.</strong> To provide primary health care,
                        follow up immunizations and pregnancies, and prepare
                        reports for health planning. Information is collected
                        with the resident&apos;s (or parent/guardian&apos;s)
                        consent.
                    </p>
                    <p>
                        <strong>Who can see it.</strong> Only authorized health
                        center staff, each limited to what their role needs.
                        Barangay officials see summary numbers only. Health
                        information is encrypted, and every view, change and
                        export is logged.
                    </p>
                    <p>
                        <strong>How long.</strong> Records are kept while the
                        resident is served by the health center. Archived
                        records are kept for the retention period, then
                        permanently deleted by the administrator.
                    </p>
                    <p>
                        <strong>Your rights (RA 10173).</strong> Residents may
                        ask to see or correct their information, or withdraw
                        consent, by visiting the health center.
                    </p>
                    <p>
                        <strong>Staff responsibility.</strong> Keep your
                        password secret, log out when you leave the computer,
                        and never copy or share resident information outside the
                        health center. Misuse may be penalized under RA 10173.
                    </p>
                </div>
            </DialogContent>
        </Dialog>
    );
}

Login.layout = {
    title: 'Log in',
    description:
        'Use the username and password given by the health center administrator.',
};
