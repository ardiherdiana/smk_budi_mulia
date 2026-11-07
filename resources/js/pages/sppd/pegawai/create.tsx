import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { type SppdPageProps } from '@/types/sppd';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { type FormEventHandler } from 'react';

interface Role {
    value: string;
    label: string;
}

interface Props extends SppdPageProps {
    roles: Role[];
}

const fieldError = (message?: string) => (message ? [{ message }] : undefined);

export default function Create() {
    const { roles } = usePage<Props>().props;

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        jabatan: '',
        username: '',
        password: '',
        roles: [] as string[],
    });

    const toggleRole = (value: string) => {
        setData('roles', data.roles.includes(value) ? data.roles.filter((r) => r !== value) : [...data.roles, value]);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('sppd.pegawai.store'));
    };

    return (
        <>
            <Head title="Tambah Akun Pegawai" />

            <div className="flex max-w-xl flex-col gap-6">
                <div>
                    <h1 className="text-xl font-semibold">Tambah Akun Pegawai</h1>
                    <p className="text-sm text-muted-foreground">Buat akun baru dan tentukan role aksesnya</p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Data Akun</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit}>
                            <FieldGroup>
                                <Field>
                                    <FieldLabel htmlFor="name">Nama Lengkap</FieldLabel>
                                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                                    <FieldError errors={fieldError(errors.name)} />
                                </Field>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field>
                                        <FieldLabel htmlFor="username">Username</FieldLabel>
                                        <Input id="username" autoCapitalize="none" spellCheck={false} value={data.username} onChange={(e) => setData('username', e.target.value)} />
                                        <FieldError errors={fieldError(errors.username)} />
                                    </Field>
                                    <Field>
                                        <FieldLabel htmlFor="jabatan">Jabatan</FieldLabel>
                                        <Input id="jabatan" value={data.jabatan} onChange={(e) => setData('jabatan', e.target.value)} />
                                    </Field>
                                </div>
                                <Field>
                                    <FieldLabel htmlFor="password">Password</FieldLabel>
                                    <Input id="password" type="password" autoComplete="new-password" value={data.password} onChange={(e) => setData('password', e.target.value)} />
                                    <FieldError errors={fieldError(errors.password)} />
                                </Field>
                                <Field>
                                    <FieldLabel>Role</FieldLabel>
                                    <div className="flex flex-wrap gap-x-4 gap-y-2">
                                        {roles.map((r) => (
                                            <label key={r.value} className="flex items-center gap-2 text-sm">
                                                <Checkbox
                                                    checked={data.roles.includes(r.value)}
                                                    onCheckedChange={() => toggleRole(r.value)}
                                                />
                                                {r.label}
                                            </label>
                                        ))}
                                    </div>
                                    <FieldError errors={fieldError(errors.roles)} />
                                </Field>
                                <Field orientation="horizontal">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Menyimpan...' : 'Simpan Akun'}
                                    </Button>
                                    <Button type="button" variant="outline" nativeButton={false} render={<Link href={route('sppd.pegawai.index')} />}>
                                        Batal
                                    </Button>
                                </Field>
                            </FieldGroup>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
