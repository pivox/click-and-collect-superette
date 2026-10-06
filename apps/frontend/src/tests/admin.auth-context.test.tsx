import React from 'react';
import {render,screen,waitFor} from '@testing-library/react';
import {beforeEach,it,expect,vi} from 'vitest';
import {AdminAuthProvider,useAdminAuth} from '@/lib/auth/AdminAuthContext';
import {decodeJwtPayload} from '@/lib/services/auth.service';
vi.mock('next/navigation',()=>({useRouter:()=>({push:vi.fn(),replace:vi.fn()})}));
vi.mock('@/lib/services/auth.service',()=>({adminLogin:vi.fn(),decodeJwtPayload:vi.fn()}));
function Consumer(){const {user,isLoading}=useAdminAuth();return <span>{isLoading?'loading':user?.email??'none'}</span>;}
beforeEach(()=>{localStorage.clear();vi.clearAllMocks();});
it.each([{roles:['ROLE_ADMIN'],exp:1},{roles:['ROLE_CUSTOMER'],exp:Date.now()/1000+3600}])('rejects unusable restored admin session %o',async claims=>{
 localStorage.setItem('admin_token','token');document.cookie='admin_token=token; path=/admin';
 vi.mocked(decodeJwtPayload).mockReturnValue({...claims,email:'user@example.tn'});
 render(<AdminAuthProvider><Consumer/></AdminAuthProvider>);
 await waitFor(()=>expect(screen.getByText('none')).toBeTruthy());
 expect(localStorage.getItem('admin_token')).toBeNull();
});
