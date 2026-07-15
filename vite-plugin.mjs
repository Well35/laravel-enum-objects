import { exec } from 'node:child_process';

/**
 * Reruns the enum generator when a PHP enum changes during dev.
 */
export default function enumObjects({
    watch = null,
    command = 'php artisan enum-objects:generate',
} = {}) {
    let running = false;
    let pending = false;

    const regenerate = () => {
        if (running) {
            pending = true;
            return;
        }

        running = true;
        exec(command, (error, stdout, stderr) => {
            running = false;

            if (error) {
                console.error(`[enum-objects] ${command} failed:\n${stderr || stdout}`);
            }

            if (pending) {
                pending = false;
                regenerate();
            }
        });
    };

    return {
        name: 'enum-objects',
        apply: 'serve',
        configureServer(server) {
            const register = (paths) => {
                const dirs = paths.map((dir) => dir.replaceAll('\\', '/'));

                dirs.forEach((dir) => server.watcher.add(dir));

                server.watcher.on('all', (event, path) => {
                    const normalized = path.replaceAll('\\', '/');

                    if (normalized.endsWith('.php') && dirs.some((dir) => normalized.includes(dir))) {
                        regenerate();
                    }
                });
            };

            if (watch) {
                register(watch);
                return;
            }

            exec(`${command} --paths`, (error, stdout) => {
                try {
                    if (error) throw error;
                    register(JSON.parse(stdout));
                } catch {
                    console.error('[enum-objects] could not read enum paths from artisan. Watching app/Enums');
                    register(['app/Enums']);
                }
            });
        },
    };
}
