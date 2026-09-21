import { mkdirSync, writeFileSync, unlinkSync } from 'node:fs';
import { resolve, delimiter } from 'node:path';
import { spawn } from 'node:child_process';

// Keep development uploads inside the project's writable storage directory.
const temporary = resolve('storage/app/private/upload-tmp');
const configuration = resolve('storage/app/private/php-runtime');
mkdirSync(temporary, { recursive: true });
mkdirSync(configuration, { recursive: true });
const probe = resolve(temporary, `.write-check-${process.pid}`);
writeFileSync(probe, 'ok');
unlinkSync(probe);
writeFileSync(resolve(configuration, 'uploads.ini'), `upload_tmp_dir="${temporary.replaceAll('\\', '/')}"\n`);
const scan = process.env.PHP_INI_SCAN_DIR;
// Laravel reload mode filters PHP_INI_SCAN_DIR from its child process.
// Preserve it; restart the launcher after changing .env or PHP settings.
const child = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', '--port=8000', '--tries=1', '--no-reload'], {
    stdio: 'inherit',
    shell: process.platform === 'win32',
    env: { ...process.env, PHP_INI_SCAN_DIR: `${scan || ''}${delimiter}${configuration}` },
});
child.on('error', error => { console.error(`Unable to start PHP: ${error.message}`); process.exitCode = 1; });
child.on('exit', code => { process.exitCode = code ?? 1; });