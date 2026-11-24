<?php

// Albanian. Source: Laravel-Lang (community translations, MIT) + platform keys.
// Missing entries fall back to English; see `php artisan lang:check`.

return array (
  'accepted' => ':Attribute duhet të pranohet.',
  'accepted_if' => ':Attribute duhet të pranohet kur :other është :value.',
  'active_url' => ':Attribute nuk është adresë e saktë.',
  'after' => ':Attribute duhet të jetë datë pas :date.',
  'after_or_equal' => ':Attribute duhet të jetë datë e barabartë ose pas :date.',
  'alpha' => ':Attribute mund të përmbajë vetëm shkronja.',
  'alpha_dash' => ':Attribute mund të përmbajë vetëm shkronja, numra, dhe viza.',
  'alpha_num' => ':Attribute mund të përmbajë vetëm shkronja dhe numra.',
  'array' => ':Attribute duhet të jetë një bashkësi (array).',
  'before' => ':Attribute duhet të jetë datë para :date.',
  'before_or_equal' => ':Attribute duhet të jetë datë e barabartë ose para :date.',
  'between' => 
  array (
    'array' => ':Attribute duhet të ketë ndërmjet :min - :max elementëve.',
    'file' => ':Attribute duhet të jetë ndërmjet :min - :max kilobajtëve.',
    'numeric' => ':Attribute duhet të jetë ndërmjet :min - :max.',
    'string' => ':Attribute duhet të ketë ndërmjet :min - :max karaktereve.',
  ),
  'boolean' => 'Fusha :attribute duhet të jetë e vërtetë ose e gabuar',
  'confirmed' => ':Attribute konfirmimi nuk përputhet.',
  'current_password' => 'Fjalëkalimi është i pasaktë.',
  'date' => ':Attribute nuk është një datë e saktë.',
  'date_equals' => ':Attribute duhet të jetë datë e barabartë me :date.',
  'date_format' => ':Attribute nuk i përshtatet formatit :format.',
  'declined' => ':Attribute duhet të refuzohet.',
  'declined_if' => ':Attribute duhet të refuzohet kur :other është :value.',
  'different' => ':Attribute dhe :other duhet të jenë të ndryshme.',
  'digits' => ':Attribute duhet të ketë :digits shifra.',
  'digits_between' => ':Attribute duhet të ketë midis :min dhe :max shifra.',
  'dimensions' => ':Attribute ka dimensione të gabuara.',
  'distinct' => ':Attribute ka një vlerë të përsëritur.',
  'email' => ':Attribute formati është i pasaktë.',
  'ends_with' => ':Attribute duhet të përfundojë me një nga vlerat: :values.',
  'enum' => ':Attribute e zgjedhura është e pavlefshme.',
  'exists' => ':Attribute përzgjedhur është i/e pasaktë.',
  'file' => ':Attribute duhet të jetë një fajll.',
  'filled' => 'Fusha :attribute është e kërkuar.',
  'gt' => 
  array (
    'array' => ':Attribute duhet të ketë më shumë se :value elemente.',
    'file' => ':Attribute duhet të jetë më i/e madh/e se :value kilobajtë.',
    'numeric' => ':Attribute duhet të jetë më i/e madh/e se :value.',
    'string' => ':Attribute duhet të ketë më shumë se :value karaktere.',
  ),
  'gte' => 
  array (
    'array' => ':Attribute duhet të ketë :value ose më shumë elemente.',
    'file' => ':Attribute duhet të jetë më i/e madh/e ose i/e barabartë me :value kilobajtë.',
    'numeric' => ':Attribute duhet të jetë më i/e madh/e ose i/e barabartë me :value.',
    'string' => ':Attribute duhet të ketë :value ose më shumë karaktere.',
  ),
  'image' => ':Attribute duhet të jetë imazh.',
  'in' => ':Attribute përzgjedhur është i/e pasaktë.',
  'in_array' => ':Attribute nuk gjendet në :other.',
  'integer' => ':Attribute duhet të jetë numër i plotë.',
  'ip' => ':Attribute duhet të jetë një IP adresë.',
  'ipv4' => ':Attribute duhet të jetë një IPv4 adresë.',
  'ipv6' => ':Attribute duhet të jetë një IPv6 adresë.',
  'json' => ':Attribute duhet të ketë përmbajtje të vlefshme JSON.',
  'lt' => 
  array (
    'array' => ':Attribute duhet të ketë më pak se :value elemente.',
    'file' => ':Attribute duhet të jetë më i/e vogël se :value kilobajtë.',
    'numeric' => ':Attribute duhet të jetë më i/e vogël se :value.',
    'string' => ':Attribute duhet të ketë më pak se :value karaktere.',
  ),
  'lte' => 
  array (
    'array' => ':Attribute duhet të ketë :value ose më pak elemente.',
    'file' => ':Attribute duhet të jetë më i/e vogël ose i/e barabartë me :value kilobajtë.',
    'numeric' => ':Attribute duhet të jetë më i/e vogël ose i/e barabartë me :value.',
    'string' => ':Attribute duhet të ketë :value ose më pak karaktere.',
  ),
  'mac_address' => ':Attribute duhet të jetë një adresë MAC e vlefshme.',
  'max' => 
  array (
    'array' => ':Attribute nuk mund të ketë më tepër se :max elemente.',
    'file' => ':Attribute nuk mund të jetë më tepër se :max kilobajtë.',
    'numeric' => ':Attribute nuk mund të jetë më tepër se :max.',
    'string' => ':Attribute nuk mund të ketë më tepër se :max karaktere.',
  ),
  'mimes' => ':Attribute duhet të jetë një dokument i tipit: :values.',
  'mimetypes' => ':Attribute duhet të jetë një dokument i tipit: :values.',
  'min' => 
  array (
    'array' => ':Attribute nuk mund të ketë më pak se :min elemente.',
    'file' => ':Attribute nuk mund të jetë më pak se :min kilobajtë.',
    'numeric' => ':Attribute nuk mund të jetë më pak se :min.',
    'string' => ':Attribute nuk mund të ketë më pak se :min karaktere.',
  ),
  'multiple_of' => ':Attribute duhet të jetë shumë nga :value',
  'not_in' => ':Attribute përzgjedhur është i/e pasaktë.',
  'not_regex' => 'Formati i :attribute është i pasaktë.',
  'numeric' => ':Attribute duhet të jetë një numër.',
  'password' => 
  array (
    'letters' => ':Attribute duhet të përmbajë të paktën një shkronjë.',
    'mixed' => ':Attribute duhet të përmbajë të paktën një shkronjë të madhe dhe një shkronjë të vogël.',
    'numbers' => ':Attribute duhet të përmbajë të paktën një numër.',
    'symbols' => ':Attribute duhet të përmbajë të paktën një simbol.',
    'uncompromised' => ':Attribute e dhënë është shfaqur në një rrjedhje të dhënash. Ju lutemi zgjidhni një :attribute të ndryshme.',
  ),
  'present' => ':Attribute duhet të jetë prezent/e.',
  'prohibited' => 'Fusha :attribute është e ndaluar.',
  'prohibited_if' => 'Fusha :attribute është e ndaluar kur :other është :value.',
  'prohibited_unless' => 'Fusha :attribute është e ndaluar nëse :other është në :values.',
  'prohibits' => 'Fusha :attribute ndalon :other të jenë të pranishëm.',
  'regex' => 'Formati i :attribute është i pasaktë.',
  'required' => 'Fusha :attribute është e kërkuar.',
  'required_array_keys' => 'Fusha :attribute duhet të përmbajë shënime për: :values.',
  'required_if' => 'Fusha :attribute është e kërkuar kur :other është :value.',
  'required_unless' => 'Fusha :attribute është e kërkuar përveç kur :other është në :values.',
  'required_with' => 'Fusha :attribute është e kërkuar kur :values ekziston.',
  'required_with_all' => 'Fusha :attribute është e kërkuar kur :values ekziston.',
  'required_without' => 'Fusha :attribute është e kërkuar kur :values nuk ekziston.',
  'required_without_all' => 'Fusha :attribute është e kërkuar kur nuk ekziston asnjë nga :values.',
  'same' => ':Attribute dhe :other duhet të përputhen.',
  'size' => 
  array (
    'array' => ':Attribute duhet të ketë :size elemente.',
    'file' => ':Attribute duhet të jetë :size kilobajtë.',
    'numeric' => ':Attribute duhet të jetë :size.',
    'string' => ':Attribute duhet të ketë :size karaktere.',
  ),
  'starts_with' => ':Attribute duhet të fillojë me njërën nga vlerat: :values.',
  'string' => ':Attribute duhet të jetë varg.',
  'timezone' => ':Attribute duhet të jetë zonë kohore e saktë.',
  'unique' => ':Attribute është marrë tashmë.',
  'uploaded' => ':Attribute dështoi të ngarkohej.',
  'url' => 'Formati i :attribute është i pasaktë.',
  'uuid' => ':Attribute duhet të jetë UUID i/e saktë.',
  'custom' => 
  array (
    'attribute-name' => 
    array (
      'rule-name' => 'custom-message',
    ),
  ),
  'attributes' => 
  array (
  ),
);
