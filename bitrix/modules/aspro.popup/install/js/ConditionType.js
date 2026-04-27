$(document).ready(function() {

	$('.modal_cond_type').closest('tr[id^=tr]').addClass('modal_type');
	$(document).on('change', '.modal_cond_type', function() {
		var select = $(this);
		var currentValue = select.val();
		setProps(currentValue, select);
	});

	setProps();

});


function setProps() {
	var modalType = getModalType();
	if(dependProps[modalType]) {
		var props = getProps(modalType);
	}

	if(props.SHOW) {
		$(props.SHOW).show();
	}

	if(props.HIDE) {
		$(props.HIDE).hide();
	}
}

function getProps(modalType) {
	var result = {
		SHOW: [],
		HIDE: [],
	};
	if(dependProps.ALL !== undefined) {
		if(dependProps.ALL.FIELDS !== undefined) {
			dependProps.ALL.FIELDS.forEach(function(field) {
				var needHide = modalType == 'ALL' ? true : dependProps[modalType].FIELDS.indexOf(field) < 0;

				var fieldInput = $('#tr_'+field);
				if(fieldInput.length) {
					if(needHide) {
						result.HIDE.push(fieldInput[0]);
					} else {
						result.SHOW.push(fieldInput[0]);
					}
				}
			});
		}


		if(dependProps.ALL.PROPS !== undefined) {
			for(var prop in dependProps.ALL.PROPS) {
				var needHide = modalType == 'ALL' ? true : dependProps[modalType].PROPS.indexOf(prop) < 0;

				var propInput = $('#tr_PROPERTY_'+dependProps.ALL.PROPS[prop]);
				if(propInput.length) {
					if(needHide) {
						result.HIDE.push(propInput[0]);
					} else {
						result.SHOW.push(propInput[0]);
					}
				}
			}
		}
	}
	return result;
}

function getModalType() {
	var select = $('.modal_cond_type');

	if(select.length) {
		var currentValue = select.val();
	}

	if(currentValue) {
		var currentOption = select.find('option[value='+currentValue+']');

		if(currentOption.length) {
			var modalType = currentOption.data('xmlid');
		}
		
	} else {
		var modalType = 'ALL';
	}

	return modalType;
}
