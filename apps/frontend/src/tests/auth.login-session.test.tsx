import React from 'react';
import {render,screen,waitFor} from '@testing-library/react';
import {beforeEach,it,expect,vi} from 'vitest';
import ClientLogin from '@/app/(client)/login/page';
import MerchantLogin from '@/app/merchant/login/page';
import AdminLogin from '@/app/admin/login/page';
const {replace,login}=vi.hoisted(()=>({replace:vi.fn(),login:vi.fn()}));
let user: object|null=null;
let merchant: object|null=null;
let isLoading=false;
let redirect='/orders/order-one';
vi.mock('next/navigation',()=>({useRouter:()=>({replace,push:vi.fn()}),useSearchParams:()=>new URLSearchParams({redirect})}));
vi.mock('@/lib/auth/ClientAuthContext',()=>({useClientAuth:()=>({user,isLoading,login})}));
vi.mock('@/lib/auth/AdminAuthContext',()=>({useAdminAuth:()=>({user,isLoading,login})}));
vi.mock('@/lib/auth/MerchantAuthContext',()=>({useMerchantAuth:()=>({merchant,isLoading,login})}));
beforeEach(()=>{vi.clearAllMocks();user=null;merchant=null;isLoading=false;redirect='/orders/order-one';});
it.each([ClientLogin,MerchantLogin,AdminLogin])('hides login while restoring session',Page=>{
 isLoading=true;render(<Page/>);expect(screen.queryByLabelText('Email')).toBeNull();
});
it('resumes the client destination from an existing session',async()=>{
 user={email:'client@example.tn'};render(<ClientLogin/>);
 await waitFor(()=>expect(replace).toHaveBeenCalledWith('/orders/order-one'));
 expect(screen.queryByLabelText('Email')).toBeNull();
});
it('reuses the merchant session and respects first-login lock',async()=>{
 merchant={password_change_required:true};render(<MerchantLogin/>);
 await waitFor(()=>expect(replace).toHaveBeenCalledWith('/merchant/premiere-connexion'));
 expect(screen.queryByLabelText('Email')).toBeNull();
});
it('reuses the admin session',async()=>{
 user={email:'admin@example.tn'};render(<AdminLogin/>);
 await waitFor(()=>expect(replace).toHaveBeenCalledWith('/admin/dashboard'));
 expect(screen.queryByLabelText('Email')).toBeNull();
});
it.each(['/login','/register','/merchant','//evil.test','/\\evil.test','/orders/../login'])('rejects unsafe client return %s',async path=>{
 user={};redirect=path;render(<ClientLogin/>);
 await waitFor(()=>expect(replace).toHaveBeenCalledWith('/'));
});
