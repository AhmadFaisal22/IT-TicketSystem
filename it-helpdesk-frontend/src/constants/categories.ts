export interface SubCategoryHint {
  en: string
  zh: string
  /** Key of an admin-managed resource file served by /api/resources/{key} */
  resourceKey: string
}

export interface SubCategory {
  id: string
  en: string
  zh: string
  hint?: SubCategoryHint
}

export interface Category {
  id: string
  emoji: string
  short_en: string
  short_zh: string
  en: string
  zh: string
  subs: SubCategory[]
}

export const CATEGORIES: Category[] = [
  {
    id: 'hardware',
    emoji: '🖥️',
    short_en: 'Hardware',
    short_zh: '硬件',
    en: 'Hardware Issues',
    zh: '硬件问题',
    subs: [
      { id: 'laptop_desktop', en: 'Laptop/Desktop not working', zh: '笔记本/台式机故障' },
      { id: 'printer', en: 'Printer issues', zh: '打印机问题' },
      { id: 'peripheral', en: 'Peripheral devices (mouse, keyboard, webcam)', zh: '外设问题（鼠标/键盘/摄像头）' },
      { id: 'hardware_upgrade', en: 'Hardware upgrade/replacement', zh: '硬件升级/更换' },
      { id: 'server_hardware', en: 'Server hardware problems', zh: '服务器硬件问题' },
    ],
  },
  {
    id: 'software',
    emoji: '💻',
    short_en: 'Software',
    short_zh: '软件',
    en: 'Software Issues',
    zh: '软件问题',
    subs: [
      { id: 'app_not_working', en: 'Application not working', zh: '应用程序无法运行' },
      { id: 'installation', en: 'Software installation/uninstallation', zh: '软件安装/卸载' },
      { id: 'bugs_errors', en: 'Software bugs/errors', zh: '软件故障/错误' },
      { id: 'license', en: 'License activation issues', zh: '许可证激活问题' },
      { id: 'version_upgrade', en: 'Version upgrade requests', zh: '版本升级申请' },
      { id: 'erp', en: 'ERP', zh: 'ERP 系统' },
      { id: 'wms', en: 'WMS', zh: 'WMS 仓储系统' },
      { id: 'mes', en: 'MES', zh: 'MES 制造执行系统' },
      { id: 'sharepoint', en: 'SharePoint', zh: 'SharePoint' },
      { id: 'office', en: 'Office', zh: 'Office 办公软件' },
      { id: 'onedrive', en: 'OneDrive', zh: 'OneDrive' },
      { id: 'camera', en: 'Camera', zh: '摄像头' },
    ],
  },
  {
    id: 'network',
    emoji: '🌐',
    short_en: 'Network',
    short_zh: '网络',
    en: 'Network & Connectivity',
    zh: '网络与连接',
    subs: [
      { id: 'internet', en: 'Internet connectivity problems', zh: '网络连接问题' },
      { id: 'vpn', en: 'VPN access issues', zh: 'VPN 访问问题' },
      { id: 'wifi', en: 'Wi-Fi issues', zh: 'Wi-Fi 问题' },
      { id: 'slow_network', en: 'Network slow performance', zh: '网络速度慢' },
      { id: 'dns_ip', en: 'DNS / IP configuration issues', zh: 'DNS / IP 配置问题' },
    ],
  },
  {
    id: 'access',
    emoji: '🔐',
    short_en: 'Access & ID',
    short_zh: '访问管理',
    en: 'Access & Identity Management',
    zh: '访问与身份管理',
    subs: [
      { id: 'password_reset', en: 'Password reset', zh: '密码重置' },
      { id: 'account_lockout', en: 'Account lockout', zh: '账户锁定' },
      { id: 'new_access', en: 'New user access request', zh: '新用户访问申请' },
      { id: 'permission_changes', en: 'Permission/role changes', zh: '权限/角色变更' },
      { id: 'mfa', en: 'MFA (Multi-Factor Authentication) issues', zh: '多因素认证问题' },
    ],
  },
  {
    id: 'email',
    emoji: '📧',
    short_en: 'Email',
    short_zh: '邮件',
    en: 'Email & Communication',
    zh: '邮件与通讯',
    subs: [
      { id: 'email_not_working', en: 'Email not sending/receiving', zh: '邮件无法发送/接收' },
      { id: 'outlook', en: 'Outlook/Exchange issues', zh: 'Outlook/Exchange 问题' },
      { id: 'spam', en: 'Spam/phishing reports', zh: '垃圾邮件/钓鱼邮件举报' },
      { id: 'mailbox_access', en: 'Mailbox access requests', zh: '邮箱访问申请' },
      { id: 'distribution_list', en: 'Distribution list changes', zh: '通讯组列表变更' },
      {
        id: 'new_email_account',
        en: 'New email account application',
        zh: '新邮箱账号申请',
        hint: {
          en: 'Please fill out the Email Account Application form:',
          zh: '请填写邮箱账号申请表：',
          resourceKey: 'email_account_application',
        },
      },
    ],
  },
  {
    id: 'data',
    emoji: '🗄️',
    short_en: 'Data',
    short_zh: '数据',
    en: 'Data & Storage',
    zh: '数据与存储',
    subs: [
      { id: 'file_access', en: 'File/folder access issues', zh: '文件/文件夹访问问题' },
      { id: 'data_loss', en: 'Data loss/recovery', zh: '数据丢失/恢复' },
      { id: 'backup', en: 'Backup requests', zh: '备份申请' },
      { id: 'storage_capacity', en: 'Storage capacity issues', zh: '存储空间不足' },
      { id: 'shared_drive', en: 'Shared drive problems', zh: '共享盘问题' },
    ],
  },
  {
    id: 'cloud',
    emoji: '☁️',
    short_en: 'Cloud',
    short_zh: '云服务',
    en: 'Cloud Services',
    zh: '云服务',
    subs: [
      { id: 'm365_gws', en: 'Microsoft 365 / Google Workspace', zh: 'Microsoft 365 / Google Workspace' },
      { id: 'cloud_app', en: 'Cloud app access problems', zh: '云应用访问问题' },
      { id: 'azure_aws', en: 'Azure / AWS issues', zh: 'Azure / AWS 问题' },
      { id: 'license_assignment', en: 'License assignment', zh: '许可证分配' },
    ],
  },
  {
    id: 'security',
    emoji: '🛡️',
    short_en: 'Security',
    short_zh: '安全',
    en: 'Security Incidents',
    zh: '安全事件',
    subs: [
      { id: 'malware', en: 'Malware/virus infection', zh: '恶意软件/病毒感染' },
      { id: 'suspicious', en: 'Suspicious activity', zh: '可疑活动' },
      { id: 'unauthorized', en: 'Unauthorized access', zh: '未授权访问' },
      { id: 'security_alert', en: 'Security alert investigation', zh: '安全告警调查' },
      { id: 'device_compliance', en: 'Device compliance issues', zh: '设备合规问题' },
    ],
  },
  {
    id: 'system',
    emoji: '🔧',
    short_en: 'System',
    short_zh: '系统',
    en: 'System & Server Issues',
    zh: '系统与服务器',
    subs: [
      { id: 'server_down', en: 'Server downtime', zh: '服务器宕机' },
      { id: 'app_server', en: 'Application server errors', zh: '应用服务器错误' },
      { id: 'database', en: 'Database issues', zh: '数据库问题' },
      { id: 'performance', en: 'System performance problems', zh: '系统性能问题' },
      { id: 'maintenance', en: 'Scheduled maintenance requests', zh: '计划维护申请' },
    ],
  },
  {
    id: 'service_request',
    emoji: '📦',
    short_en: 'Service Req.',
    short_zh: '服务申请',
    en: 'Service Requests (Non-Incident)',
    zh: '服务申请（非故障）',
    subs: [
      { id: 'new_hardware', en: 'New hardware request', zh: '新硬件申请' },
      { id: 'software_request', en: 'Software request', zh: '软件申请' },
      { id: 'license_request', en: 'License', zh: '许可证申请' },
      { id: 'access_request', en: 'Access requests', zh: '访问申请' },
      { id: 'new_setup', en: 'New system setup', zh: '新系统部署' },
      {
        id: 'asset_allocation',
        en: 'Onboarding',
        zh: '员工入职',
        hint: {
          en: 'Please fill out the IT Resource Application form:',
          zh: '请填写 IT 资源申请表：',
          resourceKey: 'onboarding_template',
        },
      },
    ],
  },
  {
    id: 'change',
    emoji: '🔄',
    short_en: 'Change',
    short_zh: '变更',
    en: 'Change Management',
    zh: '变更管理',
    subs: [
      { id: 'system_changes', en: 'System changes', zh: '系统变更' },
      { id: 'patch', en: 'Patch updates', zh: '补丁更新' },
      { id: 'config', en: 'Configuration updates', zh: '配置更新' },
      { id: 'planned_maintenance', en: 'Planned maintenance changes', zh: '计划维护变更' },
    ],
  },
  {
    id: 'monitoring',
    emoji: '📊',
    short_en: 'Monitoring',
    short_zh: '监控',
    en: 'Monitoring & Alerts',
    zh: '监控与告警',
    subs: [
      { id: 'system_alerts', en: 'System-generated alerts', zh: '系统自动告警' },
      { id: 'performance_breach', en: 'Performance threshold breaches', zh: '性能阈值超限' },
      { id: 'capacity_warning', en: 'Capacity warnings', zh: '容量预警' },
    ],
  },
  {
    id: 'mobile',
    emoji: '📱',
    short_en: 'Mobile',
    short_zh: '移动端',
    en: 'Mobile & Remote Access',
    zh: '移动端与远程访问',
    subs: [
      { id: 'mobile_device', en: 'Mobile device issues', zh: '移动设备问题' },
      { id: 'mdm', en: 'MDM (Mobile Device Management)', zh: '移动设备管理（MDM）' },
      { id: 'remote_desktop', en: 'Remote desktop issues', zh: '远程桌面问题' },
      { id: 'byod', en: 'BYOD support', zh: '自带设备支持（BYOD）' },
    ],
  },
  {
    id: 'app_support',
    emoji: '🧩',
    short_en: 'App Support',
    short_zh: '应用支持',
    en: 'Application-Specific Support',
    zh: '特定应用支持',
    subs: [
      { id: 'erp', en: 'ERP system issues', zh: 'ERP 系统问题' },
      { id: 'crm', en: 'CRM problems', zh: 'CRM 问题' },
      { id: 'internal_apps', en: 'Internal business applications', zh: '内部业务应用' },
      { id: 'api_integration', en: 'API/service integration issues', zh: 'API/服务集成问题' },
    ],
  },
  {
    id: 'general',
    emoji: '❓',
    short_en: 'General',
    short_zh: '一般咨询',
    en: 'General Inquiry / Others',
    zh: '一般咨询/其他',
    subs: [
      { id: 'how_to', en: 'How-to questions', zh: '使用指南咨询' },
      { id: 'policy', en: 'IT policy clarification', zh: 'IT 政策说明' },
      { id: 'device_purchase', en: 'Device purchase', zh: '设备采购' },
      { id: 'misc', en: 'Miscellaneous requests', zh: '其他杂项请求' },
      { id: 'other', en: 'Other', zh: '其他' },
    ],
  },
]

export function getCategoryLabel(id: string | null, locale: string): string {
  if (!id) return ''
  const cat = CATEGORIES.find(c => c.id === id)
  if (!cat) return id
  return locale === 'zh' ? cat.zh : cat.en
}

export function getSubCategoryLabel(catId: string | null, subId: string | null, locale: string): string {
  if (!catId || !subId) return ''
  const cat = CATEGORIES.find(c => c.id === catId)
  if (!cat) return subId
  const sub = cat.subs.find(s => s.id === subId)
  if (!sub) return subId
  return locale === 'zh' ? sub.zh : sub.en
}
