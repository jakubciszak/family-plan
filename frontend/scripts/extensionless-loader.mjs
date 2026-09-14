export async function resolve(specifier, context, next) {
    try {
        return await next(specifier, context);
    } catch (error) {
        if (specifier.startsWith('.') && !specifier.endsWith('.js')) {
            return next(`${specifier}.js`, context);
        }
        throw error;
    }
}
