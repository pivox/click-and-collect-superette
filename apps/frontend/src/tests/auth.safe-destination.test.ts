import {describe,it,expect} from 'vitest';
import {safeLoginDestination} from '@/lib/auth/loginPortal';
describe('role-scoped login destinations',()=>{
 it.each(['/merchant/login','/admin/dashboard','//evil.test','/merchant/../login','/merchant/%2e%2e/login'])('rejects invalid merchant target %s',path=>{
  expect(safeLoginDestination('merchant',path)).toBe('/merchant');
 });
 it('retains permitted nested destinations',()=>{
  expect(safeLoginDestination('merchant','/merchant/commandes/one')).toBe('/merchant/commandes/one');
  expect(safeLoginDestination('admin','/admin/merchants/one')).toBe('/admin/merchants/one');
  expect(safeLoginDestination('client','/stores/one/catalog?category=food')).toBe('/stores/one/catalog?category=food');
 });
});
