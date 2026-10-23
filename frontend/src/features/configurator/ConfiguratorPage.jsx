import { useEffect, useState } from 'react'
import { Alert } from '../../components/Alert'
import { Button } from '../../components/Button'
import { EmptyState } from '../../components/EmptyState'
import { LoadingState } from '../../components/LoadingState'
import { getFurnitureModel, listFurnitureModels } from '../../api/furniture'
import { DimensionControls } from './DimensionControls'
import { MaterialSelectors } from './MaterialSelectors'
import { ModelSelector } from './ModelSelector'
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

export function ConfiguratorPage() {
  const [models, setModels] = useState([])
  const [selectedSlug, setSelectedSlug] = useState(null)
  const [modelDetail, setModelDetail] = useState(null)
  const [dimensions, setDimensions] = useState({ length: 0, width: 0, height: 0 })
  const [partMaterials, setPartMaterials] = useState({})
  const [loadingList, setLoadingList] = useState(true)
  const [loadingDetail, setLoadingDetail] = useState(false)
  const [error, setError] = useState('')

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

    getFurnitureModel(selectedSlug, controller.signal)
      .then((response) => {
        if (!active) {
          return
        }

        setModelDetail(response)
        setDimensions(buildDefaultDimensions(response.dimensions))
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

  function handleReset() {
    if (!modelDetail) {
      return
    }

    setDimensions(buildDefaultDimensions(modelDetail.dimensions))
    setPartMaterials(buildDefaultSelections(modelDetail.parts))
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

  return (
    <div className="configurator-page">
      <header className="configurator-header">
        <div>
          <p className="eyebrow">Furniture configurator</p>
          <h1>Build your piece</h1>
          <p className="muted">Select a model, adjust dimensions, and choose materials for each part.</p>
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

      <div className="configurator-layout">
        <section className="configurator-controls panel" aria-label="Configuration controls">
          <ModelSelector
            models={models}
            selectedSlug={selectedSlug}
            onSelect={setSelectedSlug}
          />

          {loadingDetail || !modelDetail ? (
            <LoadingState label="Loading model details…" />
          ) : (
            <>
              {constraints ? (
                <DimensionControls
                  dimensions={dimensions}
                  constraints={constraints}
                  onChange={setDimensions}
                />
              ) : null}

              <MaterialSelectors
                parts={modelDetail.parts}
                selections={partMaterials}
                onChange={setPartMaterials}
              />

              {!allPartsHaveMaterial ? (
                <Alert tone="warning" title="Incomplete configuration">
                  One or more parts do not have a compatible material selected.
                </Alert>
              ) : null}

              <div className="estimate-placeholder">
                <strong>Production estimate</strong>
                <p className="muted">
                  Material consumption and availability will be calculated by the backend quote endpoint in a later stage.
                </p>
              </div>
            </>
          )}
        </section>

        <section className="configurator-viewport panel" aria-label="3D preview">
          {loadingDetail || !modelDetail ? (
            <LoadingState label="Preparing 3D preview…" />
          ) : (
            <Scene slug={selectedSlug} dimensions={dimensions} partMaterials={partMaterials} />
          )}
        </section>
      </div>
    </div>
  )
}
