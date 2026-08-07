export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Tenant = {
    id: string;
    name: string;
    [key: string]: unknown;
};

export type Branch = {
    id: number;
    name: string;
    code: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    tenant: Tenant | null;
    branch: Branch | null;
    branches: Branch[];
    scope: string;
    roles: string[];
    permissions: string[];
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */
