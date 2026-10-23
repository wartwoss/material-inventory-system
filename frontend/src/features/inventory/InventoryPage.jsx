import { useCallback, useEffect, useState } from 'react'
import {
  adjustStock,
  createMaterial,
  getDashboard,
  listMaterials,
  updateMaterial,
} from '../../api/materials'
import { Alert } from '../../components/Alert'
import { Button } from '../../components/Button'
import { Pagination } from '../../components/Pagination'
import { useDebouncedValue } from '../../hooks/useDebouncedValue'
import { formatQuantity } from '../../lib/format'
import { AdjustStockModal } from './AdjustStockModal'
import { MaterialFormModal } from './MaterialFormModal'
import { MaterialTable } from './MaterialTable'
import { MaterialToolbar } from './MaterialToolbar'
import { MovementHistoryModal } from './MovementHistoryModal'
import { RecentActivity } from './RecentActivity'
import { SummaryCards } from './SummaryCards'

export function InventoryPage({ refreshKey: externalRefreshKey = 0 }) {
  const [dashboard, setDashboard] = useState(null)
  const [summaryLoading, setSummaryLoading] = useState(true)
  const [dashboardError, setDashboardError] = useState('')

  const [materials, setMaterials] = useState([])
  const [meta, setMeta] = useState(null)
  const [loading, setLoading] = useState(true)
  const [listError, setListError] = useState('')

  const [search, setSearch] = useState('')
  const debouncedSearch = useDebouncedValue(search, 350)
  const [group, setGroup] = useState('')
  const [lowStockOnly, setLowStockOnly] = useState(false)
  const [page, setPage] = useState(1)
  const [perPage, setPerPage] = useState(10)
  const [refreshKey, setRefreshKey] = useState(0)

  const [modal, setModal] = useState(null)
  const [notice, setNotice] = useState(null)

  const refresh = useCallback(() => {
    setRefreshKey((value) => value + 1)
  }, [])

  useEffect(() => {
    const controller = new AbortController()
    let active = true

    setSummaryLoading(true)
    setDashboardError('')

    getDashboard(controller.signal)
      .then((response) => {
        if (active) {
          setDashboard(response)
        }
      })
      .catch((error) => {
        if (!active || error.name === 'AbortError') {
          return
        }

        setDashboardError(error.message || 'Could not load the inventory summary.')
      })
      .finally(() => {
        if (active) {
          setSummaryLoading(false)
        }
      })

    return () => {
      active = false
      controller.abort()
    }
  }, [refreshKey, externalRefreshKey])

  useEffect(() => {
    const controller = new AbortController()
    let active = true

    setLoading(true)
    setListError('')

    listMaterials(
      {
        search: debouncedSearch,
        material_group: group,
        low_stock_only: lowStockOnly,
        page,
        per_page: perPage,
      },
      controller.signal,
    )
      .then((response) => {
        if (!active) {
          return
        }

        setMaterials(response.data)
        setMeta({
          current_page: response.current_page,
          last_page: response.last_page,
          total: response.total,
          from: response.from,
          to: response.to,
          per_page: response.per_page,
        })
      })
      .catch((error) => {
        if (!active || error.name === 'AbortError') {
          return
        }

        setListError(error.message || 'Could not load materials.')
      })
      .finally(() => {
        if (active) {
          setLoading(false)
        }
      })

    return () => {
      active = false
      controller.abort()
    }
  }, [debouncedSearch, group, lowStockOnly, page, perPage, refreshKey, externalRefreshKey])

  useEffect(() => {
    if (!notice) {
      return undefined
    }

    const timer = window.setTimeout(() => setNotice(null), 6500)

    return () => window.clearTimeout(timer)
  }, [notice])

  const groups = dashboard?.groups ?? []
  const hasFilters = Boolean(search || group || lowStockOnly)

  function handleSearchChange(value) {
    setSearch(value)
    setPage(1)
  }

  function handleGroupChange(value) {
    setGroup(value)
    setPage(1)
  }

  function handleLowStockChange(value) {
    setLowStockOnly(value)
    setPage(1)
  }

  function handlePerPageChange(value) {
    setPerPage(value)
    setPage(1)
  }

  function clearFilters() {
    setSearch('')
    setGroup('')
    setLowStockOnly(false)
    setPage(1)
  }

  function showLowStock() {
    setLowStockOnly(true)
    setPage(1)
  }

  async function handleCreate(payload) {
    const material = await createMaterial(payload)
    setModal(null)
    setNotice({ tone: 'success', message: `Created ${material.name}.` })
    refresh()
  }

  async function handleUpdate(id, payload) {
    const material = await updateMaterial(id, payload)
    setModal(null)
    setNotice({ tone: 'success', message: `Updated ${material.name}.` })
    refresh()
  }

  async function handleAdjust(material, payload) {
    const updated = await adjustStock(material.id, payload)
    setModal(null)
    setNotice({
      tone: 'success',
      message: `Stock for ${updated.name} is now ${formatQuantity(updated.quantity_on_hand, {
        unit: updated.stock_unit,
      })}.`,
    })
    refresh()
  }

  return (
    <div className="app-shell">
      <header className="app-header">
        <div className="brand">
          <div className="brand-mark" aria-hidden="true">
            <span />
            <span />
            <span />
          </div>
          <div>
            <p className="eyebrow">Furniture workshop</p>
            <h1>Inventory</h1>
            <p className="app-subtitle">
              Materials, balances, and an auditable movement trail.
            </p>
          </div>
        </div>
        <div className="header-actions">
          <Button variant="primary" onClick={() => setModal({ type: 'create' })}>
            Add material
          </Button>
        </div>
      </header>

      {notice ? (
        <Alert tone={notice.tone} onDismiss={() => setNotice(null)}>
          {notice.message}
        </Alert>
      ) : null}

      {dashboardError ? (
        <Alert
          tone="warning"
          title="Summary unavailable"
          onDismiss={() => setDashboardError('')}
        >
          {dashboardError}
        </Alert>
      ) : null}

      <SummaryCards
        summary={dashboard}
        groups={groups}
        loading={summaryLoading}
        lowStockActive={lowStockOnly}
        onShowLowStock={showLowStock}
      />

      <div className="workspace">
        <section className="panel main-panel" aria-labelledby="materials-heading">
          <div className="panel-header">
            <div>
              <h2 id="materials-heading">Materials</h2>
              <p className="muted">
                {meta ? `${meta.total} material${meta.total === 1 ? '' : 's'}` : 'Loading…'}
              </p>
            </div>
          </div>

          <MaterialToolbar
            search={search}
            onSearchChange={handleSearchChange}
            group={group}
            onGroupChange={handleGroupChange}
            groups={groups}
            lowStockOnly={lowStockOnly}
            onLowStockChange={handleLowStockChange}
            perPage={perPage}
            onPerPageChange={handlePerPageChange}
            onRefresh={refresh}
            refreshing={loading || summaryLoading}
          />

          {listError ? (
            <Alert tone="error" title="Could not load materials">
              <div className="alert-row">
                <span>{listError}</span>
                <Button size="sm" onClick={refresh}>
                  Retry
                </Button>
              </div>
            </Alert>
          ) : null}

          <MaterialTable
            materials={materials}
            loading={loading}
            hasFilters={hasFilters}
            onClearFilters={clearFilters}
            onAddMaterial={() => setModal({ type: 'create' })}
            onAdjust={(material) => setModal({ type: 'adjust', material })}
            onHistory={(material) => setModal({ type: 'history', material })}
            onEdit={(material) => setModal({ type: 'edit', material })}
          />

          <div className="table-footer">
            <span className="muted">
              {meta && meta.total > 0
                ? `Showing ${meta.from}–${meta.to} of ${meta.total}`
                : 'No rows to display'}
            </span>
            <Pagination meta={meta} onPageChange={setPage} />
          </div>
        </section>

        <RecentActivity
          movements={dashboard?.recent_movements ?? []}
          stockByUnit={dashboard?.stock_by_unit ?? []}
        />
      </div>

      <footer className="app-footer">
        <span>Stage 4 — inventory + production orders</span>
        <span className="muted">
          Balances are authoritative from the Laravel API; this view never edits stock optimistically.
        </span>
      </footer>

      {modal?.type === 'create' ? (
        <MaterialFormModal
          key="create"
          mode="create"
          groups={groups}
          onClose={() => setModal(null)}
          onSubmit={handleCreate}
        />
      ) : null}

      {modal?.type === 'edit' ? (
        <MaterialFormModal
          key={`edit-${modal.material.id}`}
          mode="edit"
          material={modal.material}
          groups={groups}
          onClose={() => setModal(null)}
          onSubmit={(payload) => handleUpdate(modal.material.id, payload)}
        />
      ) : null}

      {modal?.type === 'adjust' ? (
        <AdjustStockModal
          key={`adjust-${modal.material.id}`}
          material={modal.material}
          onClose={() => setModal(null)}
          onSubmit={(payload) => handleAdjust(modal.material, payload)}
        />
      ) : null}

      {modal?.type === 'history' ? (
        <MovementHistoryModal
          key={`history-${modal.material.id}`}
          material={modal.material}
          onClose={() => setModal(null)}
        />
      ) : null}
    </div>
  )
}
