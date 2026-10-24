export const STOCK_UNITS = [
  { value: 'm2', label: 'm² — square metre' },
  { value: 'm3', label: 'm³ — cubic metre' },
  { value: 'm', label: 'm — linear metre' },
  { value: 'ea', label: 'ea — each' },
  { value: 'kg', label: 'kg — kilogram' },
]

export const UNIT_VALUES = STOCK_UNITS.map((unit) => unit.value)

export const MATERIAL_GROUP_SUGGESTIONS = [
  'wood',
  'ceramic',
  'metal',
  'upholstery',
  'glass',
  'finish',
  'hardware',
]

export const MATERIAL_PURPOSES = [
  { value: 'board', label: 'Board — panels, tops, carcasses, doors' },
  { value: 'leg', label: 'Leg — table or cabinet legs' },
  { value: 'handle', label: 'Handle — pulls and knobs' },
  { value: 'foot', label: 'Foot — standalone feet' },
  { value: 'general', label: 'General — other or unspecified uses' },
]

export const MATERIAL_PURPOSE_LABELS = {
  board: 'Board',
  leg: 'Leg',
  handle: 'Handle',
  foot: 'Foot',
  general: 'General',
}

export const MOVEMENT_TYPE_LABELS = {
  opening: 'Opening stock',
  manual_adjustment: 'Manual adjustment',
  production: 'Production consumption',
}

export const PER_PAGE_OPTIONS = [10, 15, 25, 50]
