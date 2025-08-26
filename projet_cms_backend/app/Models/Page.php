<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;


class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'parent_id',
        'sort_order',
        'status',
        'show_in_menu',
        'menu_title',
        'author_id',
        'template',
        'content_blocks',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'views_count'
    ];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'downloads_count' => 'integer'
    ];

    // Relations
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function mediables()
    {
        return $this->hasMany(Mediable::class);
    }

    // Scopes
    public function scopeImages($query)
    {
        return $query->where('mime_type', 'like', 'image/%');
    }

    public function scopeVideos($query)
    {
        return $query->where('mime_type', 'like', 'video/%');
    }

    public function scopeDocuments($query)
    {
        return $query->whereNotIn('mime_type', function ($subquery) {
            $subquery->selectRaw("'image/%'")
                     ->unionAll(function ($q) { $q->selectRaw("'video/%'"); })
                     ->unionAll(function ($q) { $q->selectRaw("'audio/%'"); });
        });
    }

    public function scopeInFolder($query, $folder)
    {
        return $query->where('folder', $folder);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('mime_type', 'like', $type . '/%');
    }

    // Accessors
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function getFullPathAttribute(): string
    {
        return Storage::disk($this->disk)->path($this->path);
    }

    public function getIsImageAttribute(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function getIsVideoAttribute(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    public function getIsAudioAttribute(): bool
    {
        return str_starts_with($this->mime_type, 'audio/');
    }

    public function getIsDocumentAttribute(): bool
    {
        return !in_array(explode('/', $this->mime_type)[0], ['image', 'video', 'audio']);
    }

    public function getHumanSizeAttribute(): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $this->size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2) . ' ' . $units[$unit];
    }

    public function getExtensionAttribute(): string
    {
        return pathinfo($this->file_name, PATHINFO_EXTENSION);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->is_image) {
            return null;
        }

        $thumbnailPath = 'thumbnails/' . pathinfo($this->path, PATHINFO_FILENAME) . '_thumb.' . $this->extension;

        if (Storage::disk($this->disk)->exists($thumbnailPath)) {
            return Storage::disk($this->disk)->url($thumbnailPath);
        }

        return $this->url;
    }

    // Méthodes utilitaires
    public function incrementDownloads(): void
    {
        $this->increment('downloads_count');
    }

    public function delete(): bool
    {
        // Supprimer le fichier physique
        Storage::disk($this->disk)->delete($this->path);

        // Supprimer les thumbnails si c'est une image
        if ($this->is_image) {
            $thumbnailPath = 'thumbnails/' . pathinfo($this->path, PATHINFO_FILENAME) . '_thumb.' . $this->extension;
            Storage::disk($this->disk)->delete($thumbnailPath);
        }

        return parent::delete();
    }

    public function move(string $newFolder): self
    {
        $oldPath = $this->path;
        $newPath = $newFolder . '/' . $this->file_name;

        Storage::disk($this->disk)->move($oldPath, $newPath);

        $this->update([
            'path' => $newPath,
            'folder' => $newFolder
        ]);

        return $this;
    }

    public function createThumbnail(int $width = 300, int $height = 300): bool
    {
        if (!$this->is_image) {
            return false;
        }

        try {
            $image = imagecreatefromstring(Storage::disk($this->disk)->get($this->path));

            $originalWidth = imagesx($image);
            $originalHeight = imagesy($image);

            // Calculer les nouvelles dimensions en gardant le ratio
            $ratio = min($width / $originalWidth, $height / $originalHeight);
            $newWidth = intval($originalWidth * $ratio);
            $newHeight = intval($originalHeight * $ratio);

            $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

            $thumbnailPath = 'thumbnails/' . pathinfo($this->path, PATHINFO_FILENAME) . '_thumb.' . $this->extension;

            ob_start();
            switch ($this->extension) {
                case 'jpg':
                case 'jpeg':
                    imagejpeg($thumbnail, null, 90);
                    break;
                case 'png':
                    imagepng($thumbnail);
                    break;
                case 'gif':
                    imagegif($thumbnail);
                    break;
            }
            $thumbnailContent = ob_get_clean();

            Storage::disk($this->disk)->put($thumbnailPath, $thumbnailContent);

            imagedestroy($image);
            imagedestroy($thumbnail);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // Méthodes statiques
    public static function createFromUpload($uploadedFile, string $folder = 'uploads', array $additionalData = []): self
    {
        $fileName = $uploadedFile->getClientOriginalName();
        $path = $uploadedFile->store($folder, 'public');

        $media = self::create(array_merge([
            'name' => pathinfo($fileName, PATHINFO_FILENAME),
            'file_name' => $fileName,
            'mime_type' => $uploadedFile->getMimeType(),
            'path' => $path,
            'disk' => 'public',
            'size' => $uploadedFile->getSize(),
            'folder' => $folder,
            'uploaded_by' => auth()->id()
        ], $additionalData));

        // Ajouter les dimensions si c'est une image
        if ($media->is_image) {
            $imagePath = Storage::disk('public')->path($path);
            $imageSize = getimagesize($imagePath);

            if ($imageSize) {
                $media->update([
                    'width' => $imageSize[0],
                    'height' => $imageSize[1]
                ]);

                // Créer automatiquement un thumbnail
                $media->createThumbnail();
            }
        }

        return $media;
    }

    public static function getByFolder(string $folder = null)
    {
        return $folder
            ? self::inFolder($folder)->orderBy('created_at', 'desc')->get()
            : self::orderBy('created_at', 'desc')->get();
    }
}

