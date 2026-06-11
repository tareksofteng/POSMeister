<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/*
 * php artisan push:vapid
 *
 * Generates a fresh VAPID keypair using openssl (no Composer dependency)
 * and prints the two .env lines the deploy person needs to paste in.
 *
 * Existing keys are NEVER overwritten silently — when VAPID_PUBLIC_KEY
 * is already set in .env, the command bails out unless --force is
 * passed. Rotating keys invalidates every existing push subscription.
 */
class GenerateVapidKeysCommand extends Command
{
    protected $signature   = 'push:vapid {--force : Overwrite an existing keypair (invalidates every subscription)}';
    protected $description = 'Generate a VAPID keypair for Web Push notifications';

    public function handle(): int
    {
        if (env('VAPID_PUBLIC_KEY') && !$this->option('force')) {
            $this->warn('VAPID keys already exist in your environment.');
            $this->line('Re-running would invalidate every push subscription on every device.');
            $this->line('Pass --force if that is genuinely what you want.');
            return self::INVALID;
        }

        if (!function_exists('openssl_pkey_new')) {
            $this->error('openssl extension is not available — cannot generate keypair.');
            return self::FAILURE;
        }

        // P-256 prime256v1 keypair — the only curve the Web Push spec
        // accepts. Private key is 32 bytes; public key is 65 (uncompressed).
        $pkey = openssl_pkey_new([
            'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ]);
        if (!$pkey) {
            $this->error('openssl_pkey_new failed — is the EC curve enabled?');
            return self::FAILURE;
        }

        openssl_pkey_export($pkey, $pem);
        $details = openssl_pkey_get_details($pkey);
        $rawPublic  = "\x04" . $details['ec']['x'] . $details['ec']['y'];
        $rawPrivate = str_pad($details['ec']['d'] ?? '', 32, "\0", STR_PAD_LEFT);

        $publicKey  = $this->base64url($rawPublic);
        $privateKey = $this->base64url($rawPrivate);

        $this->newLine();
        $this->info('VAPID keypair generated. Add these lines to your .env:');
        $this->newLine();
        $this->line("VAPID_PUBLIC_KEY={$publicKey}");
        $this->line("VAPID_PRIVATE_KEY={$privateKey}");
        $this->line("VAPID_SUBJECT=mailto:" . config('mail.from.address', 'admin@example.com'));
        $this->newLine();
        $this->comment('After saving .env, run:  php artisan config:clear');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Base64-URL-safe with no padding — what the W3C Push API expects
     * and what every browser-side `applicationServerKey` reader assumes.
     */
    private function base64url(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }
}
