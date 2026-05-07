<?php

namespace App\Services\Integrations\IdScanner;

use App\Models\Property;

/**
 * IdScanner contract — extracts identity data from a scanned/photographed ID.
 *
 * Supported document types:
 *   - aadhaar: Indian Aadhaar card (12-digit)
 *   - passport: International passport (MRZ-compatible)
 *   - driving_license: Indian DL
 *   - voter_id: Indian voter ID
 *   - pan: Indian PAN card
 *
 * Vendors:
 *   - Jumio (global, premium): https://www.jumio.com — passport + ID + selfie verification
 *   - Onfido: alternative to Jumio
 *   - Hyperverge: India-focused, Aadhaar verification + face match
 *   - Veri5: India-focused, full DigiLocker integration
 *   - Tesseract OCR: open-source, basic text extraction (no MRZ parsing)
 *
 * For Indian compliance:
 *   - Aadhaar masking required for retail-stored copies (UIDAI mandate)
 *   - Form C (foreigner registration) requires passport + visa OCR
 */
interface IdScanner
{
    public function name(): string;

    /**
     * Scan an ID image. $imageData is base64 or raw bytes.
     * Returns parsed fields:
     *   ['document_type', 'document_number', 'first_name', 'last_name',
     *    'dob', 'gender', 'address', 'issued_country', 'issued_date',
     *    'expiry_date', 'nationality', 'face_image_base64', 'confidence']
     */
    public function scan(Property $property, string $imageData, string $documentType = 'auto'): array;

    /**
     * Verify Aadhaar via UIDAI (with consent token).
     * Returns true if the supplied last4 and DoB match UIDAI records.
     * Real call requires UIDAI Authentication User Agency (AUA) registration.
     */
    public function verifyAadhaar(Property $property, string $last4, \DateTimeInterface $dob, string $name): bool;
}
