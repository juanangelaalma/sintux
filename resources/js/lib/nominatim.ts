const NOMINATIM_BASE = 'https://nominatim.openstreetmap.org';

export type NominatimResult = {
    place_id: number;
    lat: string;
    lon: string;
    display_name: string;
    address?: Record<string, string>;
};

export type AddressValue = {
    detail: string;
    rt: string;
    rw: string;
    kelurahan: string;
    kecamatan: string;
    kabupaten: string;
    provinsi: string;
    latitude: string;
    longitude: string;
};

export const INDONESIA_CENTER: [number, number] = [-2.5, 118];

export function emptyAddress(): AddressValue {
    return {
        detail: '',
        rt: '',
        rw: '',
        kelurahan: '',
        kecamatan: '',
        kabupaten: '',
        provinsi: '',
        latitude: '',
        longitude: '',
    };
}

export function isEmptyAddress(address: AddressValue): boolean {
    return Object.values(address).every(
        (value) => value === '' || value === null,
    );
}

/**
 * Map a Nominatim result into our structured address fields.
 *
 * Nominatim's administrative breakdown for Indonesia is not always aligned
 * with Desa/Kelurahan -> Kecamatan -> Kabupaten/Kota -> Provinsi, so this is a
 * best-effort mapping. All mapped fields remain editable afterwards.
 */
export function mapNominatimAddress(result: NominatimResult): AddressValue {
    const address = result.address ?? {};
    const village =
        address.village ??
        address.town ??
        address.suburb ??
        address.neighbourhood ??
        address.city_district;
    const district =
        address.county ??
        address.municipality ??
        address.state_district ??
        address.district;
    const regency = address.city ?? address.county ?? address.state_district;
    const province = address.state ?? address.province ?? address.region;

    return {
        detail: '',
        rt: '',
        rw: '',
        kelurahan: village ?? '',
        kecamatan: district ?? '',
        kabupaten: regency ?? '',
        provinsi: province ?? '',
        latitude: result.lat,
        longitude: result.lon,
    };
}

export async function searchPlaces(
    query: string,
    signal?: AbortSignal,
): Promise<NominatimResult[]> {
    if (query.trim().length < 3) {
        return [];
    }

    const url = new URL(`${NOMINATIM_BASE}/search`);
    url.searchParams.set('q', query);
    url.searchParams.set('format', 'jsonv2');
    url.searchParams.set('addressdetails', '1');
    url.searchParams.set('countrycodes', 'id');
    url.searchParams.set('limit', '8');
    url.searchParams.set('accept-language', 'id');

    try {
        const response = await fetch(url, {
            signal,
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            return [];
        }

        const data: unknown = await response.json();

        return Array.isArray(data) ? (data as NominatimResult[]) : [];
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return [];
        }

        return [];
    }
}

export async function reverseGeocode(
    latitude: number,
    longitude: number,
    signal?: AbortSignal,
): Promise<NominatimResult | null> {
    const url = new URL(`${NOMINATIM_BASE}/reverse`);
    url.searchParams.set('lat', String(latitude));
    url.searchParams.set('lon', String(longitude));
    url.searchParams.set('format', 'jsonv2');
    url.searchParams.set('addressdetails', '1');
    url.searchParams.set('zoom', '18');
    url.searchParams.set('accept-language', 'id');

    try {
        const response = await fetch(url, {
            signal,
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            return null;
        }

        const data: unknown = await response.json();

        return (data as NominatimResult) ?? null;
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return null;
        }

        return null;
    }
}
