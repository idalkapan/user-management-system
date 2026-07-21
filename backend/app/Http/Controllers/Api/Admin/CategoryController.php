<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\PostResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Kategorileri listeler.
     */
    public function index(): JsonResponse
    {
        $categories = Category::with('creator')
            ->withCount('posts')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Kategoriler başarıyla listelendi.',
            'categories' => CategoryResource::collection($categories),
        ]);
    }

    /**
     * Yeni kategori oluşturur.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $trashedCategory = Category::onlyTrashed()
            ->where('name', $validated['name'])
            ->first();

        if ($trashedCategory) {
            return response()->json([
                'message' => 'Bu isimde silinmiş bir kategori bulunuyor. Geri yükleyebilirsiniz.',
                'requires_restore' => true,
                'category' => [
                    'id' => $trashedCategory->id,
                    'name' => $trashedCategory->name,
                ],
            ], 409);
        }

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => $this->generateUniqueSlug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
            'created_by' => $request->user()->id,
        ]);

        $category->load('creator');
        $category->loadCount('posts');

        return response()->json([
            'message' => 'Kategori başarıyla oluşturuldu.',
            'category' => new CategoryResource($category),
        ], 201);
    }

    /**
     * Kategoriyi günceller.
     */
    public function update(
        UpdateCategoryRequest $request,
        Category $category
    ): JsonResponse {
        $validated = $request->validated();

        $updateData = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'],
            'is_active' => $validated['is_active'],
        ];

        if ($category->name !== $validated['name']) {
            $updateData['slug'] = $this->generateUniqueSlug(
                $validated['name'],
                $category->id
            );
        }

        $category->update($updateData);
        $category->load('creator');
        $category->loadCount('posts');

        return response()->json([
            'message' => 'Kategori başarıyla güncellendi.',
            'category' => new CategoryResource($category),
        ]);
    }

    /**
     * Kategorinin aktif/pasif durumunu günceller.
     */
    public function updateStatus(
        Request $request,
        Category $category
    ): JsonResponse {
        $validated = $request->validate(
            [
                'is_active' => 'required|boolean',
            ],
            [
                'is_active.required' => 'Aktiflik durumu belirtilmelidir.',
                'is_active.boolean' => 'Aktiflik durumu geçerli bir boolean değer olmalıdır.',
            ],
            [
                'is_active' => 'aktiflik durumu',
            ]
        );

        $category->update([
            'is_active' => $validated['is_active'],
        ]);

        $category->load('creator');
        $category->loadCount('posts');

        return response()->json([
            'message' => $validated['is_active']
                ? 'Kategori başarıyla aktif edildi.'
                : 'Kategori başarıyla pasif edildi.',
            'category' => new CategoryResource($category),
        ]);
    }

    /**
     * Kategoriye ait yazıları listeler.
     */
    public function posts(Category $category): JsonResponse
    {
        $posts = $category->posts()
            ->with(['user', 'category'])
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Kategoriye ait yazılar başarıyla listelendi.',
            'posts' => PostResource::collection($posts),
        ]);
    }

    /**
     * Soft delete edilmiş kategoriyi geri yükler.
     */
    public function restore(Category $category): JsonResponse
    {
        if (!$category->trashed()) {
            return response()->json([
                'message' => 'Bu kategori zaten aktif durumda.',
            ], 422);
        }

        $category->restore();

        $category->update([
            'is_active' => true,
        ]);

        $category->load('creator');
        $category->loadCount('posts');

        return response()->json([
            'message' => 'Kategori başarıyla geri yüklendi.',
            'category' => new CategoryResource($category),
        ]);
    }

    /**
     * Kategoriyi siler.
     */
    public function destroy(Category $category): JsonResponse
    {
        if ($category->posts()->exists()) {
            return response()->json([
                'message' => 'Bu kategoriye bağlı yazılar bulunduğu için silinemez. Kategoriyi pasif yapabilirsiniz.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Kategori başarıyla silindi.',
        ]);
    }

    /**
     * Benzersiz slug üretir.
     */
    private function generateUniqueSlug(
        string $name,
        ?int $ignoreCategoryId = null
    ): string {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 2;

        while ($this->slugExists($slug, $ignoreCategoryId)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Slug'ın kullanılıp kullanılmadığını kontrol eder.
     */
    private function slugExists(
        string $slug,
        ?int $ignoreCategoryId = null
    ): bool {
        $query = Category::withTrashed()->where('slug', $slug);

        if ($ignoreCategoryId !== null) {
            $query->where('id', '!=', $ignoreCategoryId);
        }

        return $query->exists();
    }
}
