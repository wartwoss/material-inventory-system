import { useEffect, useMemo, useState } from 'react'
import { Alert } from '../../components/Alert'
import { Button } from '../../components/Button'
import { FormField } from '../../components/FormField'
import { Modal } from '../../components/Modal'
import { listMovements } from '../../api/materials'
import { MATERIAL_GROUP_SUGGESTIONS, STOCK_UNITS } from '../../lib/constants'

const EMPTY_FORM = {
  name: '',
  sku: '',
  material_group: '',
  stock_unit: 'm2',
  opening_quantity: '',
  reorder_level: '',
  visual_key: '',
  is_active: true,
}

function toForm(material) {
  return {
    name: material.name ?? '',
    sku: material.sku ?? '',
    material_group: material.material_group ?? '',
    stock_unit: material.stock_unit ?? 'm2',
    opening_quantity: '',
    reorder_level:
      material.reorder_level === null || material.reorder_level === undefined
        ? ''
        : String(material.reorder_level),
    visual_key: material.visual_key ?? '',
    is_active: Boolean(material.is_active),
  }
}

function isDecimal(value) {
  return /^\d+(\.\d{1,6})?$/.test(String(value).trim())
}

export function MaterialFormModal({ mode, material, groups, onClose, onSubmit }) {
  const isEdit = mode === 'edit'
  const [form, setForm] = useState(() => (isEdit ? toForm(material) : EMPTY_FORM))
  const [errors, setErrors] = useState({})
  const [formError, setFormError] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [unitState, setUnitState] = useState({
    loading: isEdit,
    hasMovements: false,
  })

  useEffect(() => {
    if (!isEdit) {
      return undefined
    }

    let active = true

    listMovements(material.id, { per_page: 1 })
      .then((response) => {
        if (active) {
          setUnitState({ loading: false, hasMovements: response.total > 0 })
        }
      })
      .catch(() => {
        if (active) {
          setUnitState({ loading: false, hasMovements: false })
        }
      })

    return () => {
      active = false
    }
  }, [isEdit, material])

  const groupSuggestions = useMemo(() => {
    const values = new Set([
      ...MATERIAL_GROUP_SUGGESTIONS,
      ...groups.map((entry) => entry.material_group),
      form.material_group,
    ])

    return [...values].filter(Boolean)
  }, [groups, form.material_group])

  function update(field, value) {
    setForm((previous) => ({ ...previous, [field]: value }))
    setErrors((previous) => ({ ...previous, [field]: undefined }))
  }

  function validate() {
    const next = {}

    if (!form.name.trim()) {
      next.name = 'Material name is required.'
    }

    if (!form.material_group.trim()) {
      next.material_group = 'Choose or enter a material group.'
    }

    if (!form.stock_unit) {
      next.stock_unit = 'Choose a stock unit.'
    }

    if (form.opening_quantity !== '' && !isDecimal(form.opening_quantity)) {
      next.opening_quantity = 'Enter a non-negative number with up to 6 decimals.'
    }

    if (form.reorder_level !== '' && !isDecimal(form.reorder_level)) {
      next.reorder_level = 'Enter a non-negative number with up to 6 decimals.'
    }

    return next
  }

  async function handleSubmit(event) {
    event.preventDefault()
    const nextErrors = validate()

    if (Object.keys(nextErrors).length > 0) {
      setErrors(nextErrors)
      return
    }

    setSubmitting(true)
    setFormError('')
    setErrors({})

    const payload = {
      name: form.name.trim(),
      sku: form.sku.trim() || null,
      material_group: form.material_group.trim(),
      stock_unit: form.stock_unit,
      reorder_level: form.reorder_level === '' ? null : String(form.reorder_level),
      visual_key: form.visual_key.trim() || null,
      is_active: form.is_active,
    }

    if (!isEdit) {
      payload.opening_quantity =
        form.opening_quantity === '' ? 0 : String(form.opening_quantity)
    }

    try {
      await onSubmit(payload)
    } catch (error) {
      const fieldErrors = error.errors ?? {}
      const entries = Object.entries(fieldErrors)

      if (entries.length > 0) {
        setErrors(
          Object.fromEntries(
            entries.map(([field, messages]) => [
              field,
              Array.isArray(messages) ? messages[0] : String(messages),
            ]),
          ),
        )
      } else {
        setFormError(error.message || 'Could not save the material.')
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <Modal
      title={isEdit ? `Edit ${material.name}` : 'Add material'}
      description={
        isEdit
          ? 'Update catalogue details. Stock balances change only through adjustments.'
          : 'Create a catalogue entry and optionally record its opening stock.'
      }
      onClose={onClose}
      size="lg"
    >
      <form className="form" onSubmit={handleSubmit} noValidate>
        {formError ? (
          <Alert tone="error" onDismiss={() => setFormError('')}>
            {formError}
          </Alert>
        ) : null}

        <div className="form-grid">
          <FormField label="Material name" required error={errors.name}>
            {(props) => (
              <input
                {...props}
                type="text"
                value={form.name}
                onChange={(event) => update('name', event.target.value)}
                maxLength={255}
                autoFocus
                placeholder="e.g. Oak board, 20 mm"
              />
            )}
          </FormField>

          <FormField label="SKU" error={errors.sku} hint="Optional internal stock code.">
            {(props) => (
              <input
                {...props}
                type="text"
                value={form.sku}
                onChange={(event) => update('sku', event.target.value)}
                maxLength={255}
                placeholder="e.g. OAK-020"
              />
            )}
          </FormField>

          <FormField label="Material group" required error={errors.material_group}>
            {(props) => (
              <>
                <input
                  {...props}
                  type="text"
                  list="material-group-options"
                  value={form.material_group}
                  onChange={(event) => update('material_group', event.target.value)}
                  maxLength={255}
                  placeholder="e.g. wood"
                />
                <datalist id="material-group-options">
                  {groupSuggestions.map((group) => (
                    <option key={group} value={group} />
                  ))}
                </datalist>
              </>
            )}
          </FormField>

          <FormField
            label="Stock unit"
            required
            error={errors.stock_unit}
            hint={
              unitState.loading
                ? 'Checking whether this material has movements…'
                : unitState.hasMovements
                  ? 'Locked because this material already has stock movements.'
                  : 'Canonical unit used for every balance and movement.'
            }
          >
            {(props) => (
              <select
                {...props}
                value={form.stock_unit}
                onChange={(event) => update('stock_unit', event.target.value)}
                disabled={isEdit && (unitState.loading || unitState.hasMovements)}
              >
                {STOCK_UNITS.map((unit) => (
                  <option key={unit.value} value={unit.value}>
                    {unit.label}
                  </option>
                ))}
              </select>
            )}
          </FormField>

          {isEdit ? null : (
            <FormField
              label="Opening quantity"
              error={errors.opening_quantity}
              hint="Creates an opening-stock movement when greater than zero."
            >
              {(props) => (
                <input
                  {...props}
                  type="number"
                  min="0"
                  step="0.000001"
                  value={form.opening_quantity}
                  onChange={(event) => update('opening_quantity', event.target.value)}
                  placeholder="0"
                />
              )}
            </FormField>
          )}

          <FormField
            label="Reorder level"
            error={errors.reorder_level}
            hint="Low-stock warnings trigger at or below this quantity."
          >
            {(props) => (
              <input
                {...props}
                type="number"
                min="0"
                step="0.000001"
                value={form.reorder_level}
                onChange={(event) => update('reorder_level', event.target.value)}
                placeholder="Optional"
              />
            )}
          </FormField>

          <FormField
            label="Visual key"
            error={errors.visual_key}
            hint="Optional appearance reference for the 3D configurator."
          >
            {(props) => (
              <input
                {...props}
                type="text"
                value={form.visual_key}
                onChange={(event) => update('visual_key', event.target.value)}
                maxLength={255}
                placeholder="e.g. oak-natural"
              />
            )}
          </FormField>

          <FormField label="Availability">
            {(props) => (
              <label className="checkbox-label compact" htmlFor={props.id}>
                <input
                  {...props}
                  id={props.id}
                  type="checkbox"
                  checked={form.is_active}
                  onChange={(event) => update('is_active', event.target.checked)}
                />
                <span>Available for selection and production</span>
              </label>
            )}
          </FormField>
        </div>

        <div className="form-actions">
          <Button onClick={onClose} disabled={submitting}>
            Cancel
          </Button>
          <Button type="submit" variant="primary" disabled={submitting}>
            {submitting ? 'Saving…' : isEdit ? 'Save changes' : 'Create material'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}
