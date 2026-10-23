import { useMemo } from 'react'
import { getCachedMaterial } from '../materials/visualMaterials'

function toMeters(mm) {
  return mm / 1000
}

export function BedsideModel({ dimensions, partMaterials }) {
  const { length: lMm, width: wMm, height: hMm } = dimensions

  const length = toMeters(lMm)
  const width = toMeters(wMm)
  const height = toMeters(hMm)

  const panelThickness = 0.018
  const footHeight = 0.06
  const footWidth = 0.04
  const bodyHeight = Math.max(0.01, height - footHeight)

  const carcassMaterial = useMemo(
    () => getCachedMaterial(partMaterials.carcass),
    [partMaterials.carcass],
  )
  const doorMaterial = useMemo(
    () => getCachedMaterial(partMaterials.door),
    [partMaterials.door],
  )
  const handleMaterial = useMemo(
    () => getCachedMaterial(partMaterials.handle),
    [partMaterials.handle],
  )
  const feetMaterial = useMemo(
    () => getCachedMaterial(partMaterials.feet),
    [partMaterials.feet],
  )

  const footOffsetX = length / 2 - footWidth / 2 - 0.02
  const footOffsetZ = width / 2 - footWidth / 2 - 0.02

  const footPositions = [
    [footOffsetX, footHeight / 2, footOffsetZ],
    [-footOffsetX, footHeight / 2, footOffsetZ],
    [footOffsetX, footHeight / 2, -footOffsetZ],
    [-footOffsetX, footHeight / 2, -footOffsetZ],
  ]

  return (
    <group>
      <mesh
        castShadow
        receiveShadow
        position={[0, footHeight + bodyHeight / 2, 0]}
        material={carcassMaterial}
      >
        <boxGeometry args={[length, bodyHeight, width]} />
      </mesh>

      <mesh
        castShadow
        receiveShadow
        position={[0, footHeight + bodyHeight / 2, width / 2 + panelThickness / 2]}
        material={doorMaterial}
      >
        <boxGeometry args={[length - panelThickness * 2, bodyHeight - panelThickness, panelThickness]} />
      </mesh>

      <mesh
        castShadow
        position={[length / 4, footHeight + bodyHeight * 0.6, width / 2 + panelThickness + 0.015]}
        material={handleMaterial}
      >
        <sphereGeometry args={[0.015, 16, 16]} />
      </mesh>

      {footPositions.map((position, index) => (
        <mesh key={index} castShadow position={position} material={feetMaterial}>
          <boxGeometry args={[footWidth, footHeight, footWidth]} />
        </mesh>
      ))}
    </group>
  )
}
