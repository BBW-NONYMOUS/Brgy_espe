import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import {
    Copy,
    KeyRound,
    Lock,
    LockOpen,
    MoreHorizontal,
    Pencil,
    Plus,
    UserCog,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { ConfirmDialog } from '@/components/app/confirm-dialog';
import { DataTable } from '@/components/app/data-table';
import { EmptyState } from '@/components/app/empty-state';
import { CheckboxField, SelectField, TextField } from '@/components/app/fields';
import { PageHeader } from '@/components/app/page-header';
import { SearchFilters } from '@/components/app/search-filters';
import { StatusBadge } from '@/components/app/status-badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Spinner } from '@/components/ui/spinner';
import { useTableQuery } from '@/hooks/use-table-query';
import { formatRelative } from '@/lib/format';
import { index } from '@/routes/users';
import type { Paginated, Role, StaffUser } from '@/types';

type Filters = {
    search: string;
    role_id: number | null;
    status: string | null;
};

type Props = {
    users: Paginated<StaffUser>;
    filters: Filters;
    roles: Role[];
};

export default function UsersIndex({ users, filters, roles }: Props) {
    const page = usePage();
    const { query, update, setPage, reset, processing } = useTableQuery(
        filters,
        { only: ['users', 'filters'] },
    );
    const [editing, setEditing] = useState<StaffUser | 'new' | null>(null);
    const [resetting, setResetting] = useState<StaffUser | null>(null);
    const [busy, setBusy] = useState(false);
    const [temporary, setTemporary] = useState<{
        username: string;
        password: string;
    } | null>(null);

    // The server flashes a temporary password exactly once after create/reset.
    useEffect(() => {
        if (page.flash.temporaryPassword) {
            setTemporary(page.flash.temporaryPassword);
        }
    }, [page.flash.temporaryPassword]);

    const columns = useMemo<ColumnDef<StaffUser, unknown>[]>(
        () => [
            {
                id: 'name',
                header: 'Name',
                cell: ({ row }) => (
                    <div>
                        <div className="font-bold">{row.original.name}</div>
                        <div className="font-mono text-sm text-muted-foreground">
                            {row.original.username}
                        </div>
                    </div>
                ),
            },
            {
                id: 'role',
                header: 'Role',
                cell: ({ row }) => (
                    <StatusBadge
                        tone={
                            row.original.role?.name === 'admin'
                                ? 'info'
                                : 'primary'
                        }
                    >
                        {row.original.role?.label}
                    </StatusBadge>
                ),
            },
            {
                id: 'status',
                header: 'Status',
                cell: ({ row }) => {
                    const u = row.original;

                    return (
                        <div className="flex flex-wrap gap-1">
                            {u.is_active ? (
                                <StatusBadge tone="success">Active</StatusBadge>
                            ) : (
                                <StatusBadge tone="neutral">
                                    Deactivated
                                </StatusBadge>
                            )}
                            {u.is_locked && (
                                <StatusBadge
                                    tone="danger"
                                    icon={<Lock aria-hidden />}
                                >
                                    Locked
                                </StatusBadge>
                            )}
                            {u.must_change_password && (
                                <StatusBadge tone="warning">
                                    Must change password
                                </StatusBadge>
                            )}
                        </div>
                    );
                },
            },
            {
                id: 'last_login',
                header: 'Last login',
                meta: { hideOnMobile: true },
                cell: ({ row }) =>
                    row.original.last_login_at
                        ? formatRelative(row.original.last_login_at)
                        : 'Never',
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Actions</span>,
                meta: { cellClassName: 'w-12 text-right' },
                cell: ({ row }) => {
                    const u = row.original;

                    return (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Actions for ${u.name}`}
                                >
                                    <MoreHorizontal aria-hidden />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem
                                    onSelect={() => setEditing(u)}
                                >
                                    <Pencil aria-hidden /> Edit
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => setResetting(u)}
                                >
                                    <KeyRound aria-hidden /> Reset password
                                </DropdownMenuItem>
                                {u.is_locked && (
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            router.post(
                                                UserController.unlock.url(u.id),
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <LockOpen aria-hidden /> Unlock
                                    </DropdownMenuItem>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    );
                },
            },
        ],
        [],
    );

    return (
        <>
            <Head title="User management" />
            <div className="flex flex-1 flex-col gap-5 p-4 md:p-6">
                <PageHeader
                    title="User management"
                    description="Staff accounts and roles. Accounts are deactivated, never deleted, so every record keeps its author."
                    actions={
                        <Button onClick={() => setEditing('new')}>
                            <Plus aria-hidden /> Add user
                        </Button>
                    }
                />

                <SearchFilters
                    search={{
                        value: query.search,
                        onChange: (v) =>
                            update({ search: v }, { debounced: true }),
                        placeholder: 'Name or username',
                        label: 'Search users',
                    }}
                    filters={[
                        {
                            key: 'role',
                            label: 'Role',
                            allLabel: 'All roles',
                            options: roles.map((r) => ({
                                value: r.id,
                                label: r.label,
                            })),
                            value: query.role_id,
                            onChange: (v) =>
                                update({ role_id: v ? Number(v) : null }),
                        },
                        {
                            key: 'status',
                            label: 'Status',
                            allLabel: 'Any status',
                            options: [
                                { value: 'active', label: 'Active' },
                                { value: 'inactive', label: 'Deactivated' },
                                { value: 'locked', label: 'Locked' },
                            ],
                            value: query.status,
                            onChange: (v) => update({ status: v }),
                        },
                    ]}
                    hasActiveFilters={Boolean(
                        query.search || query.role_id || query.status,
                    )}
                    onReset={() =>
                        reset({ search: '', role_id: null, status: null })
                    }
                />

                <DataTable
                    columns={columns}
                    data={users}
                    onPageChange={setPage}
                    itemLabel="accounts"
                    caption="Staff accounts"
                    processing={processing}
                    empty={
                        <EmptyState
                            icon={UserCog}
                            title="No accounts match your search"
                        />
                    }
                />
            </div>

            {editing && (
                <UserDialog
                    user={editing === 'new' ? null : editing}
                    roles={roles}
                    onClose={() => setEditing(null)}
                />
            )}

            {resetting && (
                <ConfirmDialog
                    open
                    onOpenChange={(o) => !o && setResetting(null)}
                    title={`Reset the password of ${resetting.name}?`}
                    description={
                        <p>
                            A new temporary password will be shown once. The
                            account is unlocked and must set a new password at
                            next login.
                        </p>
                    }
                    confirmLabel="Reset password"
                    destructive
                    processing={busy}
                    onConfirm={() =>
                        router.post(
                            UserController.resetPassword.url(resetting.id),
                            {},
                            {
                                preserveScroll: true,
                                onStart: () => setBusy(true),
                                onFinish: () => {
                                    setBusy(false);
                                    setResetting(null);
                                },
                            },
                        )
                    }
                />
            )}

            <TemporaryPasswordDialog
                value={temporary}
                onClose={() => setTemporary(null)}
            />
        </>
    );
}

function UserDialog({
    user,
    roles,
    onClose,
}: {
    user: StaffUser | null;
    roles: Role[];
    onClose: () => void;
}) {
    const { auth } = usePage().props;
    const self = user?.id === auth.user.id;
    const form = useForm({
        username: user?.username ?? '',
        name: user?.name ?? '',
        email: user?.email ?? '',
        role_id: user?.role ? String(user.role.id) : '',
        is_active: user?.is_active ?? true,
    });
    const role = roles.find((r) => String(r.id) === form.data.role_id);

    return (
        <Dialog open onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <form
                    className="space-y-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.submit(
                            user
                                ? UserController.update(user.id)
                                : UserController.store(),
                            { preserveScroll: true, onSuccess: onClose },
                        );
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>
                            {user ? `Edit ${user.name}` : 'Add user'}
                        </DialogTitle>
                        <DialogDescription>
                            {user
                                ? 'Change the account details, role or status.'
                                : 'A temporary password is generated and shown once after saving.'}
                        </DialogDescription>
                    </DialogHeader>
                    <TextField
                        id="name"
                        label="Full name"
                        required
                        value={form.data.name}
                        onChange={(v) => form.setData('name', v)}
                        error={form.errors.name}
                    />
                    <TextField
                        id="username"
                        label="Username"
                        required
                        autoCapitalize="none"
                        value={form.data.username}
                        onChange={(v) =>
                            form.setData('username', v.toLowerCase())
                        }
                        error={form.errors.username}
                        description="Lowercase letters, numbers and dots, e.g. bhw.maria"
                    />
                    <TextField
                        id="email"
                        label="Email (optional)"
                        type="email"
                        value={form.data.email}
                        onChange={(v) => form.setData('email', v)}
                        error={form.errors.email}
                    />
                    <SelectField
                        id="role_id"
                        label="Role"
                        required
                        disabled={self}
                        value={form.data.role_id}
                        onChange={(v) => form.setData('role_id', v)}
                        options={roles.map((r) => ({
                            value: r.id,
                            label: r.label,
                        }))}
                        error={form.errors.role_id}
                        description={
                            self
                                ? 'You cannot change your own role.'
                                : (role?.description ?? undefined)
                        }
                    />
                    {user && !self && (
                        <CheckboxField
                            id="is_active"
                            label="Account is active (unticking blocks login immediately)"
                            checked={form.data.is_active}
                            onChange={(v) => form.setData('is_active', v)}
                        />
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            {user ? 'Save changes' : 'Create account'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function TemporaryPasswordDialog({
    value,
    onClose,
}: {
    value: { username: string; password: string } | null;
    onClose: () => void;
}) {
    const [copied, setCopied] = useState(false);

    return (
        <Dialog open={value !== null} onOpenChange={(o) => !o && onClose()}>
            <DialogContent
                showCloseButton={false}
                onInteractOutside={(e) => e.preventDefault()}
            >
                <DialogHeader>
                    <DialogTitle>Temporary password</DialogTitle>
                    <DialogDescription>
                        Give this to the staff member in person. It will not be
                        shown again.
                    </DialogDescription>
                </DialogHeader>
                {value && (
                    <div className="space-y-4">
                        <dl className="grid grid-cols-[7rem_1fr] gap-2">
                            <dt className="text-muted-foreground">Username</dt>
                            <dd className="font-mono font-bold">
                                {value.username}
                            </dd>
                            <dt className="text-muted-foreground">Password</dt>
                            <dd className="font-mono text-2xl font-bold tracking-wider select-all">
                                {value.password}
                            </dd>
                        </dl>
                        <Alert>
                            <AlertDescription>
                                They will be asked to choose their own password
                                the first time they log in.
                            </AlertDescription>
                        </Alert>
                    </div>
                )}
                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => {
                            void navigator.clipboard
                                ?.writeText(value?.password ?? '')
                                .then(() => setCopied(true));
                        }}
                    >
                        <Copy aria-hidden />{' '}
                        {copied ? 'Copied' : 'Copy password'}
                    </Button>
                    <Button
                        type="button"
                        onClick={() => {
                            setCopied(false);
                            onClose();
                        }}
                    >
                        I have written it down
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'User management', href: index() }],
};
