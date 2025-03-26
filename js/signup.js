jQuery(document).ready(function($) {
	var membershipFields = [
		'#field_1_17',
		'#field_1_18',
		'#field_1_19',
		'#field_1_52',
		'#field_1_56',
		'#field_1_57'
	];

	var needsCertification = function(val) {
		if (val.match(/^member/))
		{
			$('#input_1_37').val('').change();
		}
		else
		{
			$('#input_1_37').val('yes').change();
		}
	};

	gform.addAction(
		'gform_post_conditional_logic_field_action',
		function(formID, action, targetID, defaultValues, isInit) {
			if (action != 'show' || formID != 1 || isInit)
			{
				return;
			}

			if ($.inArray(targetID, membershipFields) == -1)
			{
				return;
			}

			needsCertification($(targetID + ' input[type=radio]:checked').val());
		}
	);

	for (var i in membershipFields)
	{
		$(membershipFields[i]).change(function() {
			needsCertification($(this).find('input[type=radio]:checked').val());
		});
	}
});
