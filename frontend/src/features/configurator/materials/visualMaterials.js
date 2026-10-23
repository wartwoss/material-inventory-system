import * as THREE from 'three'

/**
 * Explicit visual mapping for workshop materials.
 *
 * Appearance is driven by the backend's `visual_key` field and is kept
 * completely separate from inventory calculations.
 */
const VISUAL_KEY_MAP = {
  'oak-natural': { color: '#c49a6c', roughness: 0.65, metalness: 0.0 },
  walnut: { color: '#5c4033', roughness: 0.6, metalness: 0.0 },
  pine: { color: '#e8d4a8', roughness: 0.7, metalness: 0.0 },
  brass: { color: '#b5a642', roughness: 0.25, metalness: 0.8 },
  steel: { color: '#9ca3af', roughness: 0.35, metalness: 0.7 },
  'ceramic-white': { color: '#f5f5f5', roughness: 0.15, metalness: 0.0 },
  'fabric-grey': { color: '#7a7a7a', roughness: 0.95, metalness: 0.0 },
  glue: { color: '#ffffff', roughness: 0.9, metalness: 0.0 },
}

const GROUP_FALLBACK_MAP = {
  wood: { color: '#c49a6c', roughness: 0.65, metalness: 0.0 },
  metal: { color: '#9ca3af', roughness: 0.35, metalness: 0.7 },
  ceramic: { color: '#f5f5f5', roughness: 0.15, metalness: 0.0 },
  upholstery: { color: '#7a7a7a', roughness: 0.95, metalness: 0.0 },
  finish: { color: '#e5e5e5', roughness: 0.8, metalness: 0.0 },
  hardware: { color: '#b5a642', roughness: 0.25, metalness: 0.8 },
}

export function getMaterialProps(material) {
  if (!material) {
    return { color: '#e5e5e5', roughness: 0.8, metalness: 0.0 }
  }

  const key = material.visual_key?.toLowerCase()

  if (key && VISUAL_KEY_MAP[key]) {
    return VISUAL_KEY_MAP[key]
  }

  const group = material.material_group?.toLowerCase()

  if (group && GROUP_FALLBACK_MAP[group]) {
    return GROUP_FALLBACK_MAP[group]
  }

  return { color: '#e5e5e5', roughness: 0.8, metalness: 0.0 }
}

export function createMaterial(material) {
  const props = getMaterialProps(material)

  return new THREE.MeshStandardMaterial({
    color: props.color,
    roughness: props.roughness,
    metalness: props.metalness,
  })
}

const materialCache = new Map()

export function getCachedMaterial(material) {
  const key = material ? `${material.id}-${material.visual_key}` : 'default'

  if (!materialCache.has(key)) {
    materialCache.set(key, createMaterial(material))
  }

  return materialCache.get(key)
}
