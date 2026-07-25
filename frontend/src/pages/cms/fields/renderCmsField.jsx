import { DatePicker, Input, InputNumber, Select, Switch } from 'antd';
import { ICON_OPTIONS, LandingIcon } from '../../../constants/landingIcons.jsx';
import CmsIconTextListField from './CmsIconTextListField.jsx';
import CmsKeyValueListField from './CmsKeyValueListField.jsx';

const { TextArea } = Input;

function IconOption({ value }) {
  return (
    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
      <LandingIcon name={value} /> {value}
    </span>
  );
}

export function renderCmsField(field) {
  switch (field.type) {
    case 'textarea':
      return <TextArea rows={field.rows || 4} maxLength={field.maxLength} placeholder={field.placeholder} />;
    case 'richtext':
      return (
        <TextArea
          rows={field.rows || 10}
          placeholder={
            field.placeholder
            || "Gunakan baris kosong untuk paragraf baru, **teks** untuk tebal, [teks](url) untuk tautan, dan baris berawalan '- ' untuk daftar."
          }
        />
      );
    case 'number':
      return <InputNumber min={field.min} max={field.max} step={field.step} style={{ width: '100%' }} />;
    case 'switch':
      return <Switch checkedChildren="Aktif" unCheckedChildren="Nonaktif" />;
    case 'select':
      return (
        <Select
          allowClear={!field.required}
          mode={field.mode}
          options={field.options}
          placeholder={field.placeholder}
          showSearch={field.showSearch}
          optionFilterProp="label"
        />
      );
    case 'icon':
      return (
        <Select
          allowClear
          showSearch
          placeholder="Pilih ikon"
          optionFilterProp="value"
          options={ICON_OPTIONS.map((option) => ({ value: option.value, label: <IconOption value={option.value} /> }))}
        />
      );
    case 'date':
      return <DatePicker showTime={field.showTime} style={{ width: '100%' }} format={field.showTime ? 'DD/MM/YYYY HH:mm' : 'DD/MM/YYYY'} />;
    case 'keyvalue-list':
      return <CmsKeyValueListField labelPlaceholder={field.labelPlaceholder} valuePlaceholder={field.valuePlaceholder} />;
    case 'icon-text-list':
      return <CmsIconTextListField />;
    case 'text':
    default:
      return <Input maxLength={field.maxLength} placeholder={field.placeholder} />;
  }
}
