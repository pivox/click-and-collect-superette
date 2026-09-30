import { describe, expect, it } from 'vitest';
import { categoryPlaceholder } from '@/lib/category-placeholder';

describe('categoryPlaceholder (PRODUCT-IMAGE-002)', () => {
  it('mappe les catégories prioritaires vers un pictogramme neutre', () => {
    expect(categoryPlaceholder('fruits-legumes', 'Fruits et légumes')).toBe('🥬');
    expect(categoryPlaceholder('pain', 'Pain / boulangerie')).toBe('🥖');
    expect(categoryPlaceholder('produits-laitiers', 'Produits laitiers')).toBe('🥛');
    expect(categoryPlaceholder('epicerie-salee', 'Épicerie salée')).toBe('🥫');
    expect(categoryPlaceholder('epicerie-sucree', 'Épicerie sucrée')).toBe('🍬');
    expect(categoryPlaceholder('boissons', 'Boissons')).toBe('🥤');
    expect(categoryPlaceholder('vrac', 'Vrac')).toBe('🌾');
    expect(categoryPlaceholder('surgeles', 'Surgelés')).toBe('❄️');
    expect(categoryPlaceholder('hygiene', 'Hygiène')).toBe('🧼');
    expect(categoryPlaceholder('entretien', 'Entretien')).toBe('🧽');
    expect(categoryPlaceholder('bebe', 'Bébé')).toBe('🍼');
    expect(categoryPlaceholder('animaux', 'Animaux')).toBe('🐾');
    expect(categoryPlaceholder('depannage', 'Dépannage')).toBe('🔦');
  });

  it('matche par le nom français quand le slug est technique', () => {
    expect(categoryPlaceholder('cat-42', 'Légumes frais')).toBe('🥬');
    expect(categoryPlaceholder(null, 'Boulangerie')).toBe('🥖');
  });

  it('ignore les accents et la casse', () => {
    expect(categoryPlaceholder(null, 'SURGELÉS')).toBe('❄️');
    expect(categoryPlaceholder(null, 'Épicerie Salée')).toBe('🥫');
  });

  it('retombe sur le panier neutre pour une catégorie inconnue ou absente', () => {
    expect(categoryPlaceholder('inconnu', 'Zone mystère')).toBe('🛒');
    expect(categoryPlaceholder(null, null)).toBe('🛒');
    expect(categoryPlaceholder(undefined, undefined)).toBe('🛒');
  });
});
