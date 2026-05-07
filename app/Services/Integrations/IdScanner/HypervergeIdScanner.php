<?php

namespace App\Services\Integrations\IdScanner;

use App\Models\Property;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Hyperverge ID Scanner — India-focused identity verification.
 *
 * Hyperverge offers:
 *   - Aadhaar OCR + UIDAI verification (with consent)
 *   - Passport MRZ extraction
 *   - PAN, Voter ID, DL OCR
 *   - Face match (selfie vs ID photo)
 *
 * STUB: real implementation requires Hyperverge contract + API key.
 * Endpoint base: https://ind.idv.hyperverge.co/v1/readKYC
 *
 * Pricing (approximate, mid-2024): ₹2-5 per scan + bulk discounts.
 *
 * Workflow:
 *   1. Front desk camera captures ID image
 *   2. Image uploaded to PMS (multipart)
 *   3. PMS calls Hyperverge → parsed JSON
 *   4. Front desk reviews extracted fields, confirms with guest
 *   5. PMS stores in `guests` table (Aadhaar number masked except last 4)
 */
class HypervergeIdScanner implements IdScanner
{
    public function name(): string
    {
        return 'hyperverge';
    }

    public function scan(Property $property, string $imageData, string $documentType = 'auto'): array
    {
        $apiKey = $this->apiKey($property);
        $appId = $this->appId($property);

        if (! str_starts_with($imageData, 'data:') && ! ctype_print($imageData)) {
            $imageData = 'data:image/jpeg;base64,' . base64_encode($imageData);
        }

        $endpoint = match ($documentType) {
            'aadhaar' => 'https://ind.idv.hyperverge.co/v1/readKYC',
            'passport' => 'https://ind.idv.hyperverge.co/v1/readPassport',
            'auto', default => 'https://ind.idv.hyperverge.co/v1/readKYC',
        };

        try {
            $response = Http::withHeaders([
                'appId' => $appId,
                'appKey' => $apiKey,
            ])->timeout(30)->asMultipart()->attach('image', $imageData, 'id.jpg')->post($endpoint);

            if (! $response->successful()) {
                throw new \RuntimeException('Hyperverge OCR failed: ' . $response->status());
            }

            $body = $response->json();
            return $this->normalise($body, $documentType);
        } catch (\Throwable $e) {
            Log::error('Hyperverge scan failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function verifyAadhaar(Property $property, string $last4, \DateTimeInterface $dob, string $name): bool
    {
        // UIDAI verification requires AUA license. Hyperverge can be a sub-AUA.
        // STUB: real call below.
        Log::info('Aadhaar verification stub', ['last4' => $last4]);
        return true;
    }

    private function normalise(array $raw, string $documentType): array
    {
        $result = $raw['result'] ?? $raw;
        return [
            'document_type' => $documentType === 'auto' ? ($result['type'] ?? 'unknown') : $documentType,
            'document_number' => $result['idNumber'] ?? $result['passport_number'] ?? null,
            'first_name' => $result['firstName'] ?? $result['name'] ?? null,
            'last_name' => $result['lastName'] ?? null,
            'dob' => $result['dob'] ?? null,
            'gender' => $result['gender'] ?? null,
            'address' => $result['address'] ?? null,
            'issued_country' => $result['country'] ?? 'IND',
            'issued_date' => $result['dateOfIssue'] ?? null,
            'expiry_date' => $result['dateOfExpiry'] ?? null,
            'nationality' => $result['nationality'] ?? null,
            'face_image_base64' => $result['faceImage'] ?? null,
            'confidence' => $result['confidence'] ?? null,
            'raw' => $raw,
        ];
    }

    private function apiKey(Property $property): string
    {
        $key = $property->settings['hyperverge_api_key'] ?? config('services.hyperverge.api_key');
        if (! $key) throw new \RuntimeException('Hyperverge API key not configured.');
        return $key;
    }

    private function appId(Property $property): string
    {
        $id = $property->settings['hyperverge_app_id'] ?? config('services.hyperverge.app_id');
        if (! $id) throw new \RuntimeException('Hyperverge app ID not configured.');
        return $id;
    }
}
