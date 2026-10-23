import { OrbitControls, PerspectiveCamera } from '@react-three/drei'
import { Canvas } from '@react-three/fiber'
import { Suspense } from 'react'
import { BedsideModel } from './models/BedsideModel'
import { TableModel } from './models/TableModel'

export function Scene({ slug, dimensions, partMaterials }) {
  return (
    <div className="scene-container">
      <Canvas shadows>
        <PerspectiveCamera makeDefault position={[1.8, 1.4, 1.8]} fov={45} />
        <OrbitControls enablePan={true} enableZoom={true} enableRotate={true} />

        <ambientLight intensity={0.6} />
        <directionalLight
          position={[2, 4, 3]}
          intensity={1.2}
          castShadow
          shadow-mapSize-width={1024}
          shadow-mapSize-height={1024}
        />
        <directionalLight position={[-2, 2, -3]} intensity={0.4} />

        <Suspense fallback={null}>
          <group rotation={[0, -Math.PI / 6, 0]}>
            {slug === 'table' ? (
              <TableModel dimensions={dimensions} partMaterials={partMaterials} />
            ) : (
              <BedsideModel dimensions={dimensions} partMaterials={partMaterials} />
            )}
          </group>
        </Suspense>

        <mesh receiveShadow rotation={[-Math.PI / 2, 0, 0]} position={[0, 0, 0]}>
          <planeGeometry args={[6, 6]} />
          <shadowMaterial opacity={0.15} />
        </mesh>
      </Canvas>
    </div>
  )
}
