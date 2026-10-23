import { Suspense, lazy, useState } from 'react'
import { LoadingState } from './components/LoadingState'

const InventoryPage = lazy(() => import('./features/inventory/InventoryPage'))
const ConfiguratorPage = lazy(() => import('./features/configurator/ConfiguratorPage'))

const PAGES = {
  inventory: 'inventory',
  configurator: 'configurator',
}

export default function App() {
  const [page, setPage] = useState(PAGES.inventory)

  return (
    <>
      <nav className="app-nav" aria-label="Main navigation">
        <div className="app-nav-inner">
          <span className="app-nav-brand">Workshop</span>
          <div className="app-nav-links">
            <button
              type="button"
              className={page === PAGES.inventory ? 'is-active' : undefined}
              onClick={() => setPage(PAGES.inventory)}
            >
              Inventory
            </button>
            <button
              type="button"
              className={page === PAGES.configurator ? 'is-active' : undefined}
              onClick={() => setPage(PAGES.configurator)}
            >
              Configurator
            </button>
          </div>
        </div>
      </nav>
      <Suspense fallback={<LoadingState label="Loading page…" />}>
        {page === PAGES.configurator ? <ConfiguratorPage /> : <InventoryPage />}
      </Suspense>
    </>
  )
}
