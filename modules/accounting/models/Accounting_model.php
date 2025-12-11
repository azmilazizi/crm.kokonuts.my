        $addedfrom = get_staff_user_id();
        if (isset($data['created_by'])) {
            $addedfrom = $data['created_by'];
            unset($data['created_by']);
        }

        $data['addedfrom'] = $addedfrom;
                $node['addedfrom'] = $addedfrom;
