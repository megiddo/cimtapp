export type FieldMap = Record<string, string[]>;

export type ActionErrorBody = {
  type: string;
  description: string | null;
  fields?: FieldMap;
};

export type ActionPayload<T> = {
  statusCode: number;
  data?: T;
  error?: ActionErrorBody;
};

export function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

export function parseActionPayload<T>(value: unknown, httpStatus = 200): ActionPayload<T> {
  if (!isRecord(value)) {
    return { statusCode: httpStatus };
  }

  const statusCode =
    typeof value.statusCode === 'number' ? value.statusCode : httpStatus;

  const payload: ActionPayload<T> = { statusCode };

  if ('data' in value) {
    payload.data = value.data as T;
  }

  if (isRecord(value.error)) {
    payload.error = {
      type: typeof value.error.type === 'string' ? value.error.type : 'SERVER_ERROR',
      description: typeof value.error.description === 'string' ? value.error.description : null,
      fields: parseFieldMap(value.error.fields)
    };
    if (payload.error.fields === undefined) {
      delete payload.error.fields;
    }
  }

  return payload;
}

export function parseFieldMap(value: unknown): FieldMap | undefined {
  if (!isRecord(value)) {
    return undefined;
  }

  const fields: FieldMap = {};
  for (const [key, messages] of Object.entries(value)) {
    if (Array.isArray(messages) && messages.every((item) => typeof item === 'string')) {
      fields[key] = messages;
    }
  }

  return Object.keys(fields).length > 0 ? fields : undefined;
}

export function fieldErrorsFrom(payload: ActionPayload<unknown>): FieldMap {
  return payload.error?.fields ?? {};
}

export function firstFieldError(fields: FieldMap, name: string): string | null {
  const list = fields[name];
  if (list === undefined || list.length === 0) {
    return null;
  }

  return list[0];
}

export function genericErrorMessage(payload: ActionPayload<unknown>, fallback = 'Something went wrong.'): string {
  const description = payload.error?.description;
  if (typeof description === 'string' && description !== '') {
    return description;
  }

  return fallback;
}

export function isUnauthenticated(payload: ActionPayload<unknown>): boolean {
  return payload.statusCode === 401 || payload.error?.type === 'UNAUTHENTICATED';
}

export function isValidationError(payload: ActionPayload<unknown>): boolean {
  return payload.statusCode === 422 || payload.error?.type === 'VALIDATION_ERROR';
}
