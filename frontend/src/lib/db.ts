import { openDB, type IDBPDatabase } from 'idb'

const DB_NAME = 'cleantrack'
const DB_VERSION = 1

export interface PendingActivity {
  uuid: string
  area_id: number
  area_name: string
  shift_id: number
  date: string
  start_time: string
  end_time: string | null
  notes: string | null
  items: Array<{ area_object_id: number; is_checked: boolean }>
  photos: Array<{ blob: Blob; type: 'before' | 'after' }>
  created_at: string
  sync_status: 'pending' | 'syncing' | 'synced' | 'failed'
}

export interface CachedArea {
  id: number
  code: string
  name: string
  category: string
  floor_name: string
  building_name: string
  checklist: Array<{
    id: number
    object_id: number
    name: string
    icon: string
    is_required: boolean
  }>
  cached_at: string
}

async function getDB(): Promise<IDBPDatabase> {
  return openDB(DB_NAME, DB_VERSION, {
    upgrade(db) {
      // Pending activities to sync
      if (!db.objectStoreNames.contains('pendingActivities')) {
        const store = db.createObjectStore('pendingActivities', { keyPath: 'uuid' })
        store.createIndex('sync_status', 'sync_status')
        store.createIndex('date', 'date')
      }

      // Cached area data for offline use
      if (!db.objectStoreNames.contains('cachedAreas')) {
        db.createObjectStore('cachedAreas', { keyPath: 'id' })
      }

      // Pending photos
      if (!db.objectStoreNames.contains('pendingPhotos')) {
        const photoStore = db.createObjectStore('pendingPhotos', { keyPath: 'id', autoIncrement: true })
        photoStore.createIndex('activity_uuid', 'activity_uuid')
      }

      // User profile cache
      if (!db.objectStoreNames.contains('userProfile')) {
        db.createObjectStore('userProfile', { keyPath: 'id' })
      }
    },
  })
}

// ===== Pending Activities =====
export async function savePendingActivity(activity: PendingActivity): Promise<void> {
  const db = await getDB()
  await db.put('pendingActivities', activity)
}

export async function getPendingActivities(): Promise<PendingActivity[]> {
  const db = await getDB()
  return db.getAllFromIndex('pendingActivities', 'sync_status', 'pending')
}

export async function updateActivitySyncStatus(uuid: string, status: string): Promise<void> {
  const db = await getDB()
  const activity = await db.get('pendingActivities', uuid)
  if (activity) {
    activity.sync_status = status
    await db.put('pendingActivities', activity)
  }
}

export async function removeSyncedActivity(uuid: string): Promise<void> {
  const db = await getDB()
  await db.delete('pendingActivities', uuid)
}

export async function getPendingCount(): Promise<number> {
  const db = await getDB()
  const pending = await db.getAllFromIndex('pendingActivities', 'sync_status', 'pending')
  return pending.length
}

// ===== Cached Areas =====
export async function cacheArea(area: CachedArea): Promise<void> {
  const db = await getDB()
  area.cached_at = new Date().toISOString()
  await db.put('cachedAreas', area)
}

export async function getCachedArea(id: number): Promise<CachedArea | undefined> {
  const db = await getDB()
  return db.get('cachedAreas', id)
}

export async function getAllCachedAreas(): Promise<CachedArea[]> {
  const db = await getDB()
  return db.getAll('cachedAreas')
}

// ===== User Profile =====
export async function cacheUserProfile(user: any): Promise<void> {
  const db = await getDB()
  await db.put('userProfile', user)
}

export async function getCachedUserProfile(): Promise<any> {
  const db = await getDB()
  const all = await db.getAll('userProfile')
  return all[0] || null
}
