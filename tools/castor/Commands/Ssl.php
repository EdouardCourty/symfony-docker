<?php

declare(strict_types=1);

namespace Tools\Castor\Commands;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\run;

#[AsTask(namespace: 'ssl', description: 'Generate self-signed SSL certificates for local HTTPS')]
function generate(
    #[AsOption(description: 'Hostname for the certificate')]
    string $hostname = 'app.local',
    #[AsOption(description: 'Automatically trust the certificate after generation (macOS only)')]
    bool $trust = false,
): void {
    $sslDir = 'infrastructure/dev/configurations/nginx/ssl';

    io()->writeln("Generating SSL certificate for {$hostname}...");

    run([
        'openssl', 'req',
        '-x509', '-nodes',
        '-newkey', 'rsa',
        '-out', "{$sslDir}/{$hostname}.crt",
        '-config', "{$sslDir}/openssl.cnf",
    ]);

    io()->success('SSL certificate generated.');

    if ($trust) {
        trust($hostname);
    } else {
        io()->note("Run 'castor ssl:trust --hostname={$hostname}' to trust it in your macOS keychain.");
    }
}

#[AsTask(namespace: 'ssl', description: 'Trust the SSL certificate in the macOS keychain (requires sudo)')]
function trust(
    #[AsOption(description: 'Hostname for the certificate')]
    string $hostname = 'app.local',
): void {
    $certPath = "infrastructure/dev/configurations/nginx/ssl/{$hostname}.crt";

    if (!\file_exists($certPath)) {
        if (!io()->confirm('No existing certificate found. Do you want to generate one now?')) {
            return;
        }

        generate($hostname);
    }

    io()->writeln('Adding SSL certificate to macOS keychain...');

    run([
        'sudo', 'security',
        'add-trusted-cert',
        '-d', '-r', 'trustRoot',
        '-k', '/Library/Keychains/System.keychain',
        $certPath,
    ]);

    io()->success('SSL certificate trusted. You may need to restart your browser.');
}
