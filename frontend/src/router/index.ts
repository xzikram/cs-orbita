import type { RouteRecordRaw } from 'vue-router'

export const routes: RouteRecordRaw[] = [
  // ===== AUTH =====
  {
    path: '/login',
    name: 'login',
    component: () => import('../pages/auth/LoginPage.vue'),
    meta: { guest: true },
  },

  // ===== MOBILE (Cleaning Service) =====
  {
    path: '/m',
    component: () => import('../layouts/MobileLayout.vue'),
    meta: { requiresAuth: true, roles: ['cleaning_service'] },
    children: [
      {
        path: '',
        name: 'mobile-dashboard',
        component: () => import('../pages/mobile/DashboardPage.vue'),
      },
      {
        path: 'scan',
        name: 'mobile-scan',
        component: () => import('../pages/mobile/ScanPage.vue'),
      },
      {
        path: 'checklist/:areaId',
        name: 'mobile-checklist',
        component: () => import('../pages/mobile/ChecklistPage.vue'),
        props: true,
      },
      {
        path: 'tasks',
        name: 'mobile-tasks',
        component: () => import('../pages/mobile/TasksPage.vue'),
      },
      {
        path: 'history',
        name: 'mobile-history',
        component: () => import('../pages/mobile/HistoryPage.vue'),
      },
      {
        path: 'profile',
        name: 'mobile-profile',
        component: () => import('../pages/mobile/ProfilePage.vue'),
      },
    ],
  },

  // ===== DASHBOARD (Supervisor/Admin/Management) =====
  {
    path: '/dashboard',
    component: () => import('../layouts/DashboardLayout.vue'),
    meta: { requiresAuth: true, roles: ['supervisor', 'administrator', 'manajemen', 'kepala_ruangan'] },
    children: [
      // Supervisor
      {
        path: '',
        name: 'supervisor-dashboard',
        component: () => import('../pages/dashboard/OverviewPage.vue'),
      },
      {
        path: 'monitoring',
        name: 'monitoring',
        component: () => import('../pages/dashboard/MonitoringPage.vue'),
      },
      {
        path: 'audit',
        name: 'audit',
        component: () => import('../pages/dashboard/AuditPage.vue'),
      },
      {
        path: 'approvals',
        name: 'approvals',
        component: () => import('../pages/dashboard/ApprovalPage.vue'),
        meta: { roles: ['supervisor', 'administrator', 'kepala_ruangan'] },
      },
      {
        path: 'heatmap',
        name: 'heatmap',
        component: () => import('../pages/dashboard/HeatmapPage.vue'),
      },

      // Admin
      {
        path: 'admin',
        name: 'admin-dashboard',
        component: () => import('../pages/admin/AdminDashboard.vue'),
        meta: { roles: ['administrator'] },
      },
      {
        path: 'admin/users',
        name: 'admin-users',
        component: () => import('../pages/admin/UsersPage.vue'),
        meta: { roles: ['administrator'] },
      },
      {
        path: 'admin/areas',
        name: 'admin-areas',
        component: () => import('../pages/admin/AreasPage.vue'),
        meta: { roles: ['administrator'] },
      },
      {
        path: 'admin/audit-access',
        name: 'admin-audit-access',
        component: () => import('../pages/admin/ManageAuditAccessPage.vue'),
        meta: { roles: ['administrator'] },
      },
      {
        path: 'admin/backup-restore',
        name: 'admin-backup-restore',
        component: () => import('../pages/admin/BackupRestorePage.vue'),
        meta: { roles: ['administrator'] },
      },
      // Management
      {
        path: 'kpi',
        name: 'kpi-dashboard',
        component: () => import('../pages/management/KpiPage.vue'),
        meta: { roles: ['manajemen', 'administrator'] },
      },

      // Kepala Ruangan
      {
        path: 'my-areas',
        name: 'my-areas',
        component: () => import('../pages/kepala-ruangan/MyAreasPage.vue'),
        meta: { roles: ['kepala_ruangan'] },
      },

      // Complaints
      {
        path: 'complaints',
        name: 'complaints',
        component: () => import('../pages/complaints/ComplaintsPage.vue'),
      },

      // Reports
      {
        path: 'reports',
        name: 'reports',
        component: () => import('../pages/reports/ReportsPage.vue'),
      },
      // Profile (shared for mobile view)
      {
        path: 'profile',
        name: 'dashboard-profile',
        component: () => import('../pages/mobile/ProfilePage.vue'),
      },
    ],
  },

  // ===== SMART TV =====
  {
    path: '/tv',
    name: 'tv-dashboard',
    component: () => import('../pages/tv/TvDashboardPage.vue'),
  },

  // ===== AUDITOR GUEST ACCESS =====
  {
    path: '/audit-access/:linkUuid',
    name: 'audit-gateway',
    component: () => import('../pages/audit/AuditGatewayPage.vue'),
  },
  {
    path: '/audit-reports/:sessionUuid',
    name: 'audit-reports',
    component: () => import('../pages/audit/AuditReportsPage.vue'),
  },

  // ===== FALLBACK =====
  {
    path: '/:pathMatch(.*)*',
    redirect: '/login',
  },
]
