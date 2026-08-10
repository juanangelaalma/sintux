import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { useEffect, useRef } from 'react';
import { MapContainer, Marker, TileLayer, useMap } from 'react-leaflet';
import {
    INDONESIA_CENTER,
    mapNominatimAddress,
    reverseGeocode,
} from '@/lib/nominatim';
import type { AddressValue } from '@/lib/nominatim';

const pinIcon = L.divIcon({
    className: '',
    html: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="36" viewBox="0 0 24 24" fill="#465fff" stroke="#2739b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3" fill="#fff"/></svg>`,
    iconSize: [28, 36],
    iconAnchor: [14, 36],
});

function FlyToController({
    latitude,
    longitude,
}: {
    latitude: number | null;
    longitude: number | null;
}) {
    const map = useMap();
    const last = useRef('');

    useEffect(() => {
        if (latitude === null || longitude === null) {
            return;
        }

        const key = `${latitude},${longitude}`;

        if (key === last.current) {
            return;
        }

        last.current = key;
        map.flyTo([latitude, longitude], 15);
    }, [latitude, longitude, map]);

    return null;
}

type AddressMapPickerProps = {
    value: AddressValue;
    onChange: (patch: Partial<AddressValue>) => void;
};

export default function AddressMapPicker({
    value,
    onChange,
}: AddressMapPickerProps) {
    const latitude = value.latitude ? Number(value.latitude) : Number.NaN;
    const longitude = value.longitude ? Number(value.longitude) : Number.NaN;
    const hasCoords =
        Number.isFinite(latitude) &&
        Number.isFinite(longitude) &&
        (latitude !== 0 || longitude !== 0);
    const position: [number, number] = hasCoords
        ? [latitude, longitude]
        : INDONESIA_CENTER;

    const handleDragEnd = async (event: L.DragEndEvent) => {
        const { lat, lng } = event.target.getLatLng();
        const patch: Partial<AddressValue> = {
            latitude: String(lat),
            longitude: String(lng),
        };

        const reversed = await reverseGeocode(lat, lng);

        if (reversed) {
            const mapped = mapNominatimAddress(reversed);
            patch.kelurahan = mapped.kelurahan;
            patch.kecamatan = mapped.kecamatan;
            patch.kabupaten = mapped.kabupaten;
            patch.provinsi = mapped.provinsi;
        }

        onChange(patch);
    };

    return (
        <div className="overflow-hidden rounded-lg border border-gray-300 dark:border-gray-700">
            <MapContainer
                center={position}
                zoom={hasCoords ? 15 : 5}
                scrollWheelZoom={false}
                className="z-0 h-64 w-full"
            >
                <TileLayer
                    url="https://tile.openstreetmap.org/{z}/{x}/{y}.png"
                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                />
                <FlyToController
                    latitude={hasCoords ? latitude : null}
                    longitude={hasCoords ? longitude : null}
                />
                <Marker
                    position={position}
                    draggable
                    icon={pinIcon}
                    eventHandlers={{ dragend: handleDragEnd }}
                />
            </MapContainer>
        </div>
    );
}
