// Registry of admin-managed downloadable files. The key is stable and is
// what the backend stores the file under (/api/resources/{key}); admins
// replace the file itself in Admin → Resource Files without code changes.
export interface ManagedResource {
  key: string
  en: string
  zh: string
  desc_en: string
  desc_zh: string
}

export const MANAGED_RESOURCES: ManagedResource[] = [
  {
    key: 'onboarding_template',
    en: 'IT Resource Application (Onboarding)',
    zh: 'IT 资源申请表（员工入职）',
    desc_en: 'Offered for download when an employee picks “Onboarding” in the ticket form.',
    desc_zh: '员工在工单表单中选择「员工入职」时提供下载。',
  },
]
