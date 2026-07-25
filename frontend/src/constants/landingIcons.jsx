import {
  AlertOutlined,
  BankOutlined,
  BulbOutlined,
  CalendarOutlined,
  CarOutlined,
  ClockCircleOutlined,
  ClusterOutlined,
  CreditCardOutlined,
  DashboardOutlined,
  DatabaseOutlined,
  EnvironmentOutlined,
  EyeOutlined,
  FileTextOutlined,
  GiftOutlined,
  GlobalOutlined,
  HeartOutlined,
  HomeOutlined,
  LikeOutlined,
  MessageOutlined,
  MobileOutlined,
  NotificationOutlined,
  PhoneOutlined,
  RocketOutlined,
  SafetyCertificateOutlined,
  SafetyOutlined,
  SmileOutlined,
  StarOutlined,
  TeamOutlined,
  ThunderboltOutlined,
  TrophyOutlined,
  WalletOutlined,
} from '@ant-design/icons';

// Single source of truth for every icon selectable throughout the landing-page
// CMS (Services, Features, Statistics, FAQ categories, ...) and for rendering
// those choices back on the public landing page.
export const ICONS = {
  AlertOutlined,
  BankOutlined,
  BulbOutlined,
  CalendarOutlined,
  CarOutlined,
  ClockCircleOutlined,
  ClusterOutlined,
  CreditCardOutlined,
  DashboardOutlined,
  DatabaseOutlined,
  EnvironmentOutlined,
  EyeOutlined,
  FileTextOutlined,
  GiftOutlined,
  GlobalOutlined,
  HeartOutlined,
  HomeOutlined,
  LikeOutlined,
  MessageOutlined,
  MobileOutlined,
  NotificationOutlined,
  PhoneOutlined,
  RocketOutlined,
  SafetyCertificateOutlined,
  SafetyOutlined,
  SmileOutlined,
  StarOutlined,
  TeamOutlined,
  ThunderboltOutlined,
  TrophyOutlined,
  WalletOutlined,
};

export const ICON_OPTIONS = Object.keys(ICONS).map((key) => ({ value: key, label: key }));

export function LandingIcon({ name, ...rest }) {
  const Component = ICONS[name];
  if (!Component) return null;
  return <Component {...rest} />;
}
