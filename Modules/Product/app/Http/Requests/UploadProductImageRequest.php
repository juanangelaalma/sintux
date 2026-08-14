<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png',
                'max:5120', // Maksimal 5MB (5120 KB)
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'File gambar produk wajib diunggah.',
            'image.file' => 'Upload harus berupa berkas berkategori file.',
            'image.image' => 'Berkas yang diunggah harus berupa gambar.',
            'image.mimes' => 'Format file gambar hanya diperbolehkan JPG (.jpg, .jpeg) dan PNG (.png).',
            'image.max' => 'Ukuran gambar maksimal adalah 5 MB.',
        ];
    }
}
