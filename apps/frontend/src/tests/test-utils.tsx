import { render, type RenderOptions } from '@testing-library/react'
import { ReactElement } from 'react'
import { ClientLocaleProvider } from '@/lib/i18n/ClientLocaleContext'

const AllTheProviders = ({ children }: { children: React.ReactNode }) => {
  return <ClientLocaleProvider>{children}</ClientLocaleProvider>
}

const customRender = (ui: ReactElement, options?: Omit<RenderOptions, 'wrapper'>) =>
  render(ui, { wrapper: AllTheProviders, ...options })

export * from '@testing-library/react'
export { customRender as render }
