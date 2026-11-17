export type Person = {
  id: string;
  name: string;
  created_at: string;
  removed_at: string | null;
};

export type VialPerson = {
  person_id: string;
  name: string;
  drawn_mg: number;
};

export type VialSummary = {
  id: string;
  peptide_id: string;
  peptide_name: string;
  display_name: string;
  lid_color_name: string;
  peptide_mg: number;
  bac_water_ml: number;
  concentration_mg_per_ml: number;
  mg_left: number;
  probably_empty: boolean;
  people: VialPerson[];
};

export type CurrentVial = VialSummary;

export type Peptide = {
  id: string;
  name: string;
  slug: string;
  removed_at: string | null;
};

export type LogDefaults = {
  person_id: string;
  logged_at: string;
  iu: number | null;
  is_last_draw: boolean;
  vial_id: string | null;
  probably_empty: boolean;
  source: { log_id: string; logged_at: string } | null;
};

export type LogEntry = {
  id: string;
  person_id: string | null;
  person_name: string;
  vial_id: string | null;
  vial_name: string;
  lid_color_name: string;
  peptide_name: string;
  bac_water_ml: number;
  peptide_mg_in_vial: number;
  concentration_mg_per_ml: number;
  iu: number;
  dose_volume_ml: number;
  dose_mg: number;
  syringe_volume_ml: number;
  syringe_capacity_iu: number;
  logged_at: string;
  is_last_draw: boolean;
  notes: string | null;
};