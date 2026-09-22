function normalizePath(path: string): string {
    const withoutQuery = path.split(/[?#]/, 1)[0] ?? path;

    if (withoutQuery.length > 1 && withoutQuery.endsWith('/')) {
        return withoutQuery.slice(0, -1);
    }

    return withoutQuery;
}

export function isActiveExact(
    currentUrl: string,
    targetPath?: string,
): boolean {
    if (!targetPath) {
        return false;
    }

    return normalizePath(currentUrl) === normalizePath(targetPath);
}

export function isActivePrefix(
    currentUrl: string,
    targetPath?: string,
): boolean {
    if (!targetPath) {
        return false;
    }

    const current = normalizePath(currentUrl);
    const target = normalizePath(targetPath);

    return current === target || current.startsWith(`${target}/`);
}
