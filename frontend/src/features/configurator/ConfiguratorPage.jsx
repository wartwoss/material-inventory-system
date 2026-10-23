import { useCallback, useEffect, useMemo, useState } from 'react'
import { Alert } from '../../components/Alert'
import { Button } from '../../components/Button'
import { EmptyState } from '../../components/EmptyState'
import { LoadingState } from '../../components/LoadingState'
import { getFurnitureModel, listFurnitureModels } from '../../api/furniture'
import { confirmProductionOrder, quoteConfiguration } from '../../api/production'
import { useDebouncedValue } from '../../hooks/useDebouncedValue'
import { DimensionControls } from './DimensionControls'
import { MaterialSelectors } from './MaterialSelectors'
import { ModelSelector } from './ModelSelector'
import { QuotePanel } from './QuotePanel'
import { Scene } from './Scene'

function buildDefaultSelections(parts) {
  const selections = {}

  parts.forEach((part) => {
    selections[part.code] = part.allowed_materials[0] ?? null
  })

  return selections
}

function buildDefaultDimensions(dimensions) {
  return {
    length: dimensions.length.default,
    width: dimensions.width.default,
    height: dimensions.height.default,
  }
}

function buildQuotePayload(modelDetail, dimensions, quantity, partMaterials) {
  return {
    furniture_model_slug: modelDetail.slug,
    length_mm: dimensions.length,
    width_mm: dimensions.width,
    height_mm: dimensions.height,
    quantity,
    parts: modelDetail.parts.map((part) => ({
      code: part.code,
      material_id: partMaterials[part.code]?.id,
    })),
  }
}

function generateIdempotencyKey() {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  return `${Date.now()}-${Math.random().toString(36).slice(2)}`
}

function quoteMatchesConfig(quote, quoteConfig) {
  if (!quote || !quoteConfig) {
    return false
  }

  if (quote.furniture_model?.slug !== quoteConfig.slug) {
    return false
  }

  if (
    quote.configuration.length_mm !== quoteConfig.length ||
    quote.configuration.width_mm !== quoteConfig.width ||
    quote.configuration.height_mm !== quoteConfig.height ||
    quote.configuration.quantity !== quoteConfig.quantity
  ) {
    return false
  }

  const quoteMaterialIds = new Set(
    quote.components.map((component) => `${component.part_code}:${component.material_id}`),
  )

  for (const [code, materialId] of Object.entries(quoteConfig.materials)) {
    if (!quoteMaterialIds.has(`${code}:${materialId}`)) {
      return false
    }
  }

  return true
}

export function ConfiguratorPage({ onRefreshInventory }) {
  const [models, setModels] = useState([])
  const [selectedSlug, setSelectedSlug] = useState(null)
  const [modelDetail, setModelDetail] = useState(null)
  const [dimensions, setDimensions] = useState({ length: 0, width: 0, height: 0 })
  const [quantity, setQuantity] = useState(1)
  const [partMaterials, setPartMaterials] = useState({})
  const [loadingList, setLoadingList] = useState(true)
  const [loadingDetail, setLoadingDetail] = useState(false)
  const [error, setError] = useState('')

  const [quote, setQuote] = useState(null)
  const [quoteLoading, setQuoteLoading] = useState(false)
  const [quoteError, setQuoteError] = useState('')

  const [idempotencyKey, setIdempotencyKey] = useState(() => generateIdempotencyKey())
  const [isConfirming, setIsConfirming] = useState(false)
  const [confirmationSuccess, setConfirmationSuccess] = useState(null)
  const [confirmationError, setConfirmationError] = useState('')

  const refreshQuote = useCallback(
    async (payload) => {
      setQuoteLoading(true)
      setQuoteError('')

      try {
        const response = await quoteConfiguration(payload)
        setQuote(response)
      } catch (requestError) {
        if (requestError.name !== 'AbortError') {
          setQuoteError(requestError.message || 'Could not calculate quote.')
        }
      } finally {
        setQuoteLoading(false)
      }
    },
    [],
  )

  useEffect(() => {
    const controller = new AbortController()
    let active = true

    setLoadingList(true)
    setError('')

    listFurnitureModels(controller.signal)
      .then((response) => {
        if (!active) {
          return
        }

        setModels(response)
      })
      .catch((requestError) => {
        if (!active || requestError.name === 'AbortError') {
          return
        }

        setError(requestError.message || 'Could not load furniture models.')
      })
      .finally(() => {
        if (active) {
          setLoadingList(false)
        }
      })

    return () => {
      active = false
      controller.abort()
    }
  }, [])

  useEffect(() => {
    if (models.length > 0 && selectedSlug === null) {
      setSelectedSlug(models[0].slug)
    }
  }, [models, selectedSlug])

  useEffect(() => {
    if (!selectedSlug) {
      return undefined
    }

    const controller = new AbortController()
    let active = true

    setLoadingDetail(true)
    setError('')
    setQuote(null)
    setQuoteError('')
    setConfirmationSuccess(null)
    setConfirmationError('')

    getFurnitureModel(selectedSlug, controller.signal)
      .then((response) => {
        if (!active) {
          return
        }

        setModelDetail(response)
        setDimensions(buildDefaultDimensions(response.dimensions))
        setQuantity(1)
        setPartMaterials(buildDefaultSelections(response.parts))
      })
      .catch((requestError) => {
        if (!active || requestError.name === 'AbortError') {
          return
        }

        setError(requestError.message || 'Could not load the selected model.')
      })
      .finally(() => {
        if (active) {
          setLoadingDetail(false)
        }
      })

    return () => {
      active = false
      controller.abort()
    }
  }, [selectedSlug])

  const quoteConfig = useMemo(() => {
    if (!modelDetail) {
      return null
    }

    return {
      slug: selectedSlug,
      length: dimensions.length,
      width: dimensions.width,
      height: dimensions.height,
      quantity,
      materials: Object.fromEntries(
        modelDetail.parts.map((part) => [part.code, partMaterials[part.code]?.id ?? null]),
      ),
    }
  }, [modelDetail, selectedSlug, dimensions, quantity, partMaterials])

  const debouncedQuoteConfig = useDebouncedValue(quoteConfig, 350)

  useEffect(() => {
    if (!debouncedQuoteConfig || !modelDetail) {
      return undefined
    }

    const allPartsHaveMaterial = modelDetail.parts.every(
      (part) => partMaterials[part.code]?.id != null,
    )

    if (!allPartsHaveMaterial) {
      setQuote(null)
      setQuoteError('')
      setQuoteLoading(false)
      return undefined
    }

    const controller = new AbortController()
    let active = true

    setQuoteLoading(true)
    setQuoteError('')

    const payload = buildQuotePayload(modelDetail, dimensions, quantity, partMaterials)

    quoteConfiguration(payload)
      .then((response) => {
        if (!active) {
          return
        }

        setQuote(response)
      })
      .catch((requestError) => {
        if (!active || requestError.name === 'AbortError') {
          return
        }

        setQuoteError(requestError.message || 'Could not calculate quote.')
      })
      .finally(() => {
        if (active) {
          setQuoteLoading(false)
        }
      })

    return () => {
      active = false
      controller.abort()
    }
  }, [debouncedQuoteConfig, modelDetail, dimensions, quantity, partMaterials])

  useEffect(() => {
    if (quoteConfig) {
      setIdempotencyKey(generateIdempotencyKey())
    }
  }, [quoteConfig])

  async function handleConfirm() {
    if (!modelDetail || !quote || quoteLoading || isConfirming) {
      return
    }

    if (!quote.is_sufficient || !quoteMatchesConfig(quote, quoteConfig)) {
      return
    }

    setIsConfirming(true)
    setConfirmationSuccess(null)
    setConfirmationError('')

    try {
      const payload = buildQuotePayload(modelDetail, dimensions, quantity, partMaterials)
      const result = await confirmProductionOrder(payload, idempotencyKey)

      setConfirmationSuccess(result)
      onRefreshInventory?.()

      const refreshedModel = await getFurnitureModel(selectedSlug)
      setModelDetail(refreshedModel)
      await refreshQuote(payload)
    } catch (requestError) {
      if (requestError.name !== 'AbortError') {
        setConfirmationError(requestError.message || 'Could not place production order.')
      }
    } finally {
      setIsConfirming(false)
    }
  }

  function handleReset() {
    if (!modelDetail) {
      return
    }

    setDimensions(buildDefaultDimensions(modelDetail.dimensions))
    setQuantity(1)
    setPartMaterials(buildDefaultSelections(modelDetail.parts))
    setQuote(null)
    setQuoteError('')
    setConfirmationSuccess(null)
    setConfirmationError('')
  }

  if (loadingList) {
    return <LoadingState label="Loading furniture models…" />
  }

  if (error && !modelDetail) {
    return (
      <Alert tone="error" title="Could not load configurator">
        {error}
      </Alert>
    )
  }

  if (models.length === 0) {
    return (
      <EmptyState
        title="No furniture models"
        description="Add furniture models and configurable parts in the backend before using the configurator."
      />
    )
  }

  const constraints = modelDetail
    ? {
        length: modelDetail.dimensions.length,
        width: modelDetail.dimensions.width,
        height: modelDetail.dimensions.height,
      }
    : null

  const allPartsHaveMaterial = modelDetail
    ? modelDetail.parts.every((part) => partMaterials[part.code])
    : false

  const quoteIsCurrent = quoteMatchesConfig(quote, quoteConfig)
  const canConfirm =
    !isConfirming &&
    !quoteLoading &&
    allPartsHaveMaterial &&
    quote != null &&
    quote.is_sufficient &&
    quoteIsCurrent

  return (
    <div className="configurator-page">
      <header className="configurator-header">
        <div>
          <p className="eyebrow">Furniture configurator</p>
          <h1>Build your piece</h1>
          <p className="muted">Select a model, adjust dimensions, choose materials, and confirm production.</p>
        </div>
        <Button variant="secondary" onClick={handleReset} disabled={!modelDetail || loadingDetail}>
          Reset to defaults
        </Button>
      </header>

      {error ? (
        <Alert tone="warning" onDismiss={() => setError('')}>
          {error}
        </Alert>
      ) : null}

      {confirmationSuccess ? (
        <Alert tone="success" title="Production order confirmed" onDismiss={() => setConfirmationSuccess(null)}>
          Order #{confirmationSuccess.id} for {confirmationSuccess.configuration.quantity} unit
          {confirmationSuccess.configuration.quantity === 1 ? '' : 's'} of{' '}
          {confirmationSuccess.furniture_model.name} was created.
        </Alert>
      ) : null}

      {confirmationError ? (
        <Alert tone="error" title="Could not place order" onDismiss={() => setConfirmationError('')}>
          {confirmationError}
        </Alert>
      ) : null}

      <div className="configurator-layout-v2">
        <main className="configurator-workspace">
          <section className="configurator-model-bar panel" aria-label="Model selection">
            <ModelSelector
              models={models}
              selectedSlug={selectedSlug}
              onSelect={setSelectedSlug}
            />
          </section>

          <section className="configurator-preview panel" aria-label="3D preview">
            {loadingDetail || !modelDetail ? (
              <LoadingState label="Preparing 3D preview…" />
            ) : (
              <Scene slug={selectedSlug} dimensions={dimensions} partMaterials={partMaterials} />
            )}
          </section>

          <section className="configurator-dimensions panel" aria-label="Dimension controls">
            {loadingDetail || !modelDetail || !constraints ? (
              <LoadingState label="Loading dimensions…" />
            ) : (
              <DimensionControls
                dimensions={dimensions}
                constraints={constraints}
                onChange={setDimensions}
              />
            )}
          </section>
        </main>

        <aside className="configurator-side-panel panel" aria-label="Configuration summary">
          {loadingDetail || !modelDetail ? (
            <LoadingState label="Loading configuration…" />
          ) : (
            <>
              <MaterialSelectors
                parts={modelDetail.parts}
                selections={partMaterials}
                onChange={setPartMaterials}
              />

              <div className="quantity-field">
                <label htmlFor="quantity">Quantity</label>
                <input
                  id="quantity"
                  type="number"
                  inputMode="numeric"
                  min={1}
                  step={1}
                  value={quantity}
                  onChange={(event) =>
                    setQuantity(Math.max(1, Math.round(Number(event.target.value)) || 1))
                  }
                />
              </div>

              {!allPartsHaveMaterial ? (
                <Alert tone="warning" title="Incomplete configuration">
                  One or more parts do not have a compatible material selected.
                </Alert>
              ) : null}

              <QuotePanel quote={quote} loading={quoteLoading} error={quoteError} />

              {allPartsHaveMaterial && quote && !quoteLoading ? (
                <div className="confirm-actions">
                  <Button
                    variant="primary"
                    onClick={handleConfirm}
                    disabled={!canConfirm || isConfirming}
                  >
                    {isConfirming ? 'Placing order…' : 'Place Production Order'}
                  </Button>
                  {!quote.is_sufficient ? (
                    <span className="muted">Insufficient stock to confirm this order.</span>
                  ) : !quoteIsCurrent ? (
                    <span className="muted">Quote is stale. Waiting for an updated quote.</span>
                  ) : null}
                </div>
              ) : null}
            </>
          )}
        </aside>
      </div>
    </div>
  )
}
