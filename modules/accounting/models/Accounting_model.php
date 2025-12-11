        $dayMonthYear = date('dmY', $timestamp);
        $prefix      = '#JE-';
        $formatted  = $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT) . '-' . $dayMonthYear;
        $dayMonthYear = date('dmY', $timestamp);
            if (preg_match('/^#JE-\d{5}-' . $dayMonthYear . '$/', $providedNumber)) {
