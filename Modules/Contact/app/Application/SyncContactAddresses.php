<?php

namespace Modules\Contact\Application;

use Modules\Contact\Models\Contact;

class SyncContactAddresses
{
    /**
     * Persist billing/shipping addresses for a contact based on the
     * "shipping same as billing" flag. Each address type is upserted when
     * provided and removed when present with an empty value. Addresses are
     * left untouched when their key is absent from the payload.
     *
     * @param  array{shipping_same_as_billing?: bool, billing_address?: array|null, shipping_address?: array|null}  $data
     */
    public function execute(Contact $contact, array $data): void
    {
        if (array_key_exists('billing_address', $data)) {
            $this->syncAddress($contact, 'billing', $data['billing_address']);
        }

        if ($contact->shipping_same_as_billing) {
            $contact->shippingAddress()->delete();

            return;
        }

        if (array_key_exists('shipping_address', $data)) {
            $this->syncAddress($contact, 'shipping', $data['shipping_address']);
        }
    }

    /**
     * @param  array{detail?: string|null, rt?: string|null, rw?: string|null, kelurahan?: string|null, kecamatan?: string|null, kabupaten?: string|null, provinsi?: string|null, latitude?: float|string|null, longitude?: float|string|null}|null  $address
     */
    private function syncAddress(Contact $contact, string $type, ?array $address): void
    {
        $relation = $type === 'billing' ? 'billingAddress' : 'shippingAddress';

        if ($address === null || $this->isEmptyAddress($address)) {
            $contact->{$relation}()->delete();

            return;
        }

        $contact->{$relation}()->updateOrCreate([], $this->payload($type, $address));
    }

    /**
     * An address is considered empty when every field is null or an empty
     * string.
     *
     * @param  array<string, mixed>  $address
     */
    private function isEmptyAddress(array $address): bool
    {
        foreach ($address as $value) {
            if ($value !== null && $value !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{detail?: string|null, rt?: string|null, rw?: string|null, kelurahan?: string|null, kecamatan?: string|null, kabupaten?: string|null, provinsi?: string|null, latitude?: float|string|null, longitude?: float|string|null}  $address
     * @return array{type: string, detail: string|null, rt: string|null, rw: string|null, kelurahan: string|null, kecamatan: string|null, kabupaten: string|null, provinsi: string|null, latitude: float|string|null, longitude: float|string|null}
     */
    private function payload(string $type, array $address): array
    {
        return [
            'type' => $type,
            'detail' => $address['detail'] ?? null,
            'rt' => $address['rt'] ?? null,
            'rw' => $address['rw'] ?? null,
            'kelurahan' => $address['kelurahan'] ?? null,
            'kecamatan' => $address['kecamatan'] ?? null,
            'kabupaten' => $address['kabupaten'] ?? null,
            'provinsi' => $address['provinsi'] ?? null,
            'latitude' => $this->nullableDecimal($address['latitude'] ?? null),
            'longitude' => $this->nullableDecimal($address['longitude'] ?? null),
        ];
    }

    /**
     * Convert empty strings to null for decimal columns.
     */
    private function nullableDecimal(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }
}
