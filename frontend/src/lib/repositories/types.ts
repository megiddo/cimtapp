export type PeptideType = {
  id: string;
  slug: string;
  name: string;
  sort_order: number;
};

export type Syringe = {
  id: string;
  label: string;
  volume_ml: number;
  capacity_iu: number;
  is_default: boolean;
  quantity: number;
  archived_at: string | null;
};

export type BacBottle = {
  id: string;
  volume_ml: number;
  remaining_ml: number;
  opened_at: string;
  notes: string | null;
  created_at: string;
  archived_at: string | null;
  is_current: boolean;
};

export type Compound = {
  id: string;
  name: string;
  is_open: boolean;
  archived_at: string | null;
  peptide_type_id: string;
  peptide_type_slug: string;
  peptide_type_name: string;
  peptide_mg: number;
  bac_water_ml: number;
  compounded_at: string;
  notes: string | null;
  created_at: string;
  bac_bottle_id: string | null;
  has_uses: boolean;
  adjustment_mg: number;
  remaining_mg: number;
  remaining_ml: number;
  remaining_iu: number;
  concentration: number;
  profile_ids: string[];
};

export type Profile = {
  id: string;
  name: string;
  is_default: boolean;
  created_at: string;
};

export type LoggedUse = {
  id: string;
  compound_id: string;
  profile_id: string | null;
  profile_name: string | null;
  compound_name: string;
  peptide_type_name: string;
  iu: number;
  syringe_id: string | null;
  syringe_label: string | null;
  syringe_volume_ml: number;
  syringe_capacity_iu: number;
  volume_ml: number;
  peptide_mg: number;
  used_at: string;
  notes: string | null;
  created_at: string;
  updated_at: string;
};
