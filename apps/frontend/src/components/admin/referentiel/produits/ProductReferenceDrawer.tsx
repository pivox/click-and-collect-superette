'use client';
import { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { AdminDrawer } from '@/components/admin/ui/AdminDrawer';
import {
  createProductReference,
  updateProductReference,
  uploadProductReferenceImage,
  deleteProductReferenceImage,
} from '@/lib/services/admin/product-references.service';
import { listBrands, createBrand } from '@/lib/services/admin/brands.service';
import { listCategories, createCategory } from '@/lib/services/admin/categories.service';
import { InlineCreateCombobox } from '@/components/admin/ui/InlineCreateCombobox';
import type {
  ProductReference,
  ProductReferenceImage,
  Brand,
  Category,
} from '@/lib/types/admin/referentiel.types';
import { mediaUrl } from '@/lib/media';

const UNITS = ['litre', 'millilitre', 'kilogramme', 'gramme', 'piece', 'paquet'] as const;
const STATUSES = ['draft', 'pending_review', 'approved', 'rejected'] as const;

const MAX_IMAGE_BYTES = 2 * 1024 * 1024;
const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

const IMAGE_ERROR_MESSAGES: Record<string, string> = {
  PRODUCT_IMAGE_TOO_SMALL: 'Image trop petite (minimum 400×400 px).',
  PRODUCT_IMAGE_TOO_LARGE: 'Fichier trop lourd (maximum 2 Mo).',
  PRODUCT_IMAGE_UNSUPPORTED_MIME: 'Format non supporté (JPEG, PNG ou WebP attendu).',
  PRODUCT_IMAGE_UNREADABLE: 'Image illisible ou corrompue.',
  PRODUCT_IMAGE_FILE_REQUIRED: 'Aucun fichier sélectionné.',
  PRODUCT_IMAGE_VARIANT_GENERATION_FAILED: 'Échec de la génération des variantes.',
};

function mapImageError(e: unknown): string {
  if (axios.isAxiosError(e)) {
    const raw = JSON.stringify(e.response?.data ?? '');
    for (const code of Object.keys(IMAGE_ERROR_MESSAGES)) {
      if (raw.includes(code)) return IMAGE_ERROR_MESSAGES[code];
    }
  }
  return "Échec de l'envoi de l'image. Réessayez.";
}

/**
 * Image management for an existing product reference: live preview, upload
 * (with client-side type/size guard mirroring the backend), and removal.
 */
function ProductImageSection({
  productId,
  initialImage,
  onImageChange,
}: {
  productId: string;
  initialImage?: ProductReferenceImage | null;
  onImageChange?: () => void;
}) {
  const [image, setImage] = useState<ProductReferenceImage | null>(initialImage ?? null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  const preview = mediaUrl(image?.card_url ?? image?.detail_url ?? null);

  const handleFile = async (file: File) => {
    setError(null);
    if (!ACCEPTED_IMAGE_TYPES.includes(file.type)) {
      setError('Format non supporté (JPEG, PNG ou WebP attendu).');
      return;
    }
    if (file.size > MAX_IMAGE_BYTES) {
      setError('Fichier trop lourd (maximum 2 Mo).');
      return;
    }
    setBusy(true);
    try {
      const { image: uploaded } = await uploadProductReferenceImage(productId, file);
      setImage(uploaded);
      onImageChange?.();
    } catch (e) {
      setError(mapImageError(e));
    } finally {
      setBusy(false);
      if (inputRef.current) inputRef.current.value = '';
    }
  };

  const handleDelete = async () => {
    setError(null);
    setBusy(true);
    try {
      await deleteProductReferenceImage(productId);
      setImage(null);
      onImageChange?.();
    } catch (e) {
      setError(mapImageError(e));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div>
      <label className="mb-1 block text-sm font-semibold">Image officielle</label>
      <div className="flex items-start gap-3">
        <div className="grid h-24 w-24 flex-shrink-0 place-items-center overflow-hidden rounded-md border border-line bg-product-tile">
          {preview ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={preview}
              alt={image?.alt ?? 'Aperçu produit'}
              className="h-full w-full object-contain"
            />
          ) : (
            <span className="text-xs text-muted">Aucune image</span>
          )}
        </div>
        <div className="flex-1 space-y-2">
          <input
            ref={inputRef}
            type="file"
            accept={ACCEPTED_IMAGE_TYPES.join(',')}
            disabled={busy}
            onChange={(e) => {
              const file = e.target.files?.[0];
              if (file) void handleFile(file);
            }}
            className="block w-full text-sm text-muted file:mr-3 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-1.5 file:text-sm file:text-white hover:file:bg-primary-dark"
          />
          <p className="text-xs text-muted">
            JPEG, PNG ou WebP · max 2 Mo · 400×400 px minimum · ratio carré recommandé.
          </p>
          {image && (
            <button
              type="button"
              onClick={() => void handleDelete()}
              disabled={busy}
              className="text-xs text-danger hover:underline disabled:opacity-50"
            >
              Supprimer l’image
            </button>
          )}
          {busy && <p className="text-xs text-muted">Traitement en cours…</p>}
          {error && <p className="text-xs text-danger">{error}</p>}
        </div>
      </div>
    </div>
  );
}

function TagInput({ tags, onChange }: { tags: string[]; onChange: (t: string[]) => void }) {
  const [input, setInput] = useState('');
  const add = () => {
    const v = input.trim();
    if (v && !tags.includes(v)) onChange([...tags, v]);
    setInput('');
  };
  return (
    <div className="flex min-h-[42px] w-full flex-wrap gap-1 rounded-md border border-line px-2 py-1 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
      {tags.map((t) => (
        <span key={t} className="flex items-center gap-1 rounded bg-soft px-2 py-0.5 text-xs">
          {t}
          <button
            type="button"
            onClick={() => onChange(tags.filter((x) => x !== t))}
            aria-label={`Supprimer ${t}`}
            className="text-muted hover:text-danger"
          >
            ✕
          </button>
        </span>
      ))}
      <input
        type="text"
        value={input}
        onChange={(e) => setInput(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); add(); }
        }}
        onBlur={add}
        placeholder="Ajouter alias…"
        className="min-w-[100px] flex-1 bg-transparent text-sm outline-none"
      />
    </div>
  );
}

interface ProductReferenceDrawerProps {
  open: boolean;
  onClose: () => void;
  product: ProductReference | null;
  /** Called after a field save: the parent reloads the list and closes the drawer. */
  onSaved: () => void;
  /** Called after a change that should refresh the list WITHOUT closing the drawer
   *  (image upload/delete, and the create step that unlocks the image section). */
  onChanged?: () => void;
}

type FormState = {
  nameFr: string; nameAr: string; variantFr: string; variantAr: string;
  brandId: string; categoryId: string; unit: string; volume: string;
  barcode: string; country: string; status: string; aliases: string[];
};

const EMPTY_FORM: FormState = {
  nameFr: '', nameAr: '', variantFr: '', variantAr: '',
  brandId: '', categoryId: '', unit: '', volume: '',
  barcode: '', country: 'TN', status: 'draft', aliases: [],
};

export function ProductReferenceDrawer({
  open, onClose, product, onSaved, onChanged,
}: ProductReferenceDrawerProps) {
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [brands, setBrands] = useState<Brand[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  // Product created within this drawer session — lets the user attach an image
  // right after creation without closing. `effectiveProduct` unifies edit + create.
  const [savedProduct, setSavedProduct] = useState<ProductReference | null>(null);
  const effectiveProduct = savedProduct ?? product;

  useEffect(() => {
    void Promise.all([listBrands(1, 50), listCategories(1, 50)])
      .then(([b, c]) => {
        setBrands(b.items);
        setCategories(c.items);
      })
      .catch(() => {
        setError('Impossible de charger les marques et catégories.');
      });
  }, []);

  useEffect(() => {
    if (product) {
      setForm({
        nameFr: product.name_fr,
        nameAr: product.name_ar ?? '',
        variantFr: product.variant_fr ?? '',
        variantAr: product.variant_ar ?? '',
        brandId: product.brand_id,
        categoryId: product.category_id,
        unit: product.unit,
        volume: product.volume ?? '',
        barcode: product.barcode ?? '',
        country: product.country,
        status: product.status === 'archived' ? 'draft' : product.status,
        aliases: product.aliases,
      });
    } else {
      setForm(EMPTY_FORM);
    }
    setSavedProduct(null);
    setError(null);
  }, [product, open]);

  const set = <K extends keyof FormState>(key: K, value: FormState[K]) =>
    setForm((f) => ({ ...f, [key]: value }));

  const handleSubmit = async () => {
    if (!form.nameFr.trim() || !form.brandId || !form.categoryId || !form.unit) {
      setError('Nom FR, Marque, Catégorie et Unité sont obligatoires.');
      return;
    }
    setIsSubmitting(true);
    setError(null);
    try {
      const payload = {
        nameFr: form.nameFr.trim(),
        nameAr: form.nameAr.trim() || undefined,
        variantFr: form.variantFr.trim() || undefined,
        variantAr: form.variantAr.trim() || undefined,
        brandId: form.brandId,
        categoryId: form.categoryId,
        unit: form.unit,
        volume: form.volume.trim() || undefined,
        barcode: form.barcode.trim() || undefined,
        country: form.country.trim() || undefined,
        status: form.status,
        aliases: form.aliases.length ? form.aliases : undefined,
      };
      if (effectiveProduct) {
        await updateProductReference(effectiveProduct.id, payload);
        onSaved();
      } else {
        // Create then stay open in edit mode so the image section unlocks for the
        // freshly created product; refresh the list without closing.
        const created = await createProductReference(payload);
        setSavedProduct(created);
        onChanged?.();
      }
    } catch (e) {
      setError(axios.isAxiosError(e) && e.response?.status === 409
        ? 'Un nom ou slug identique existe déjà.'
        : 'Une erreur est survenue. Réessayez.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const field = (
    label: string,
    key: keyof FormState,
    opts?: { required?: boolean; maxLength?: number; dir?: string; placeholder?: string },
  ) => (
    <div>
      <label className="mb-1 block text-sm font-semibold">
        {label} {opts?.required && <span className="text-danger">*</span>}
      </label>
      <input
        type="text"
        value={form[key] as string}
        onChange={(e) => set(key, e.target.value as FormState[typeof key])}
        maxLength={opts?.maxLength}
        dir={opts?.dir}
        placeholder={opts?.placeholder}
        className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
      />
    </div>
  );

  return (
    <AdminDrawer
      open={open}
      onClose={onClose}
      title={effectiveProduct ? 'Modifier le produit' : 'Nouveau produit'}
      onSubmit={handleSubmit}
      isSubmitting={isSubmitting}
      size="lg"
    >
      <div className="space-y-4">
        {error && (
          <div className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
            {error}
          </div>
        )}
        <div className="grid grid-cols-2 gap-3">
          {field('Nom FR', 'nameFr', { required: true, maxLength: 255 })}
          {field('Nom AR', 'nameAr', { maxLength: 255, dir: 'rtl' })}
          {field('Variante FR', 'variantFr', { maxLength: 160 })}
          {field('Variante AR', 'variantAr', { maxLength: 160, dir: 'rtl' })}
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div>
            <label className="mb-1 block text-sm font-semibold">
              Marque <span className="text-danger">*</span>
            </label>
            <InlineCreateCombobox
              items={brands}
              value={form.brandId}
              onChange={(id) => set('brandId', id)}
              getId={(b) => b.id}
              getLabel={(b) => b.canonical_name}
              placeholder="Rechercher ou créer une marque…"
              onCreate={async (name) => {
                const created = await createBrand({ canonicalName: name });
                setBrands((prev) => [...prev, created]);
                return created;
              }}
              disabled={isSubmitting}
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-semibold">
              Catégorie <span className="text-danger">*</span>
            </label>
            <InlineCreateCombobox
              items={categories}
              value={form.categoryId}
              onChange={(id) => set('categoryId', id)}
              getId={(c) => c.id}
              getLabel={(c) => c.name_fr}
              placeholder="Rechercher ou créer une catégorie…"
              onCreate={async (name) => {
                const created = await createCategory({ nameFr: name });
                setCategories((prev) => [...prev, created]);
                return created;
              }}
              disabled={isSubmitting}
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-semibold">
              Unité <span className="text-danger">*</span>
            </label>
            <select
              value={form.unit}
              onChange={(e) => set('unit', e.target.value)}
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
            >
              <option value="">Sélectionner…</option>
              {UNITS.map((u) => <option key={u} value={u}>{u}</option>)}
            </select>
          </div>
          {field('Volume', 'volume', { placeholder: 'ex. 1, 500ml' })}
          {field('Code-barres', 'barcode', { maxLength: 64 })}
          {field('Pays', 'country', { maxLength: 2, placeholder: 'TN' })}
        </div>
        <div>
          <label className="mb-1 block text-sm font-semibold">Statut</label>
          <select
            value={form.status}
            onChange={(e) => set('status', e.target.value)}
            className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
          >
            {STATUSES.map((s) => <option key={s} value={s}>{s}</option>)}
          </select>
        </div>
        <div>
          <label className="mb-1 block text-sm font-semibold">Aliases</label>
          <TagInput tags={form.aliases} onChange={(t) => set('aliases', t)} />
        </div>
        {effectiveProduct ? (
          <ProductImageSection
            key={effectiveProduct.id}
            productId={effectiveProduct.id}
            initialImage={effectiveProduct.image}
            onImageChange={onChanged}
          />
        ) : (
          <p className="rounded-md bg-soft px-3 py-2 text-xs text-muted">
            Enregistrez le produit pour pouvoir ajouter une image.
          </p>
        )}
      </div>
    </AdminDrawer>
  );
}
