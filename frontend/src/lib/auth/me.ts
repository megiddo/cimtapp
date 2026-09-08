export type Me = {
  email: string;
  has_password: boolean;
  has_google: boolean;
  remainder: null | unknown;
  open_vials?: unknown[];
};

export function normalizeEmail(email: string): string {
  return email.trim().toLowerCase();
}

export function isMe(value: unknown): value is Me {
  if (typeof value !== 'object' || value === null) {
    return false;
  }

  const record = value as Record<string, unknown>;
  return (
    typeof record.email === 'string' &&
    typeof record.has_password === 'boolean' &&
    typeof record.has_google === 'boolean' &&
    !('encrypted_dek' in record) &&
    !('dek_nonce' in record) &&
    !('dek' in record)
  );
}
