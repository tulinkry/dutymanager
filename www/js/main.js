$('#frmsearchForm-from_time').datetimepicker({
	format: 'YYYY-MM-DD HH:mm:ss',
	/*mask:'9999-19-39 29:59:59',
	showSecond: true,
	format:'Y-m-d H:i:00',
	formatTime: 'H:i:00',*/
});

$('#frmsearchForm-to_time').datetimepicker({
	format: 'YYYY-MM-DD HH:mm:ss',
	/*mask:'9999-19-39 29:59:59',
	showSecond: true,
	format:'Y-m-d H:i:00',
	formatTime: 'H:i:00',*/
});

$(function(){
	var show = $.fn.show
	$.fn.show = function ()
	{
		$(this).removeClass ( "hidden" );
		return show.apply(this,arguments);
	};
	var hide = $.fn.hide
	$.fn.hide = function ()
	{
		$(this).addClass ( "hidden" );
		return hide.apply(this,arguments);
	};

});

$(function() {
var tooltips = $( "[title]" ).tooltip();
});

Nette.toggle = function (id, visible) {
    var el = $('#' + id);
    if (visible) {
        el.slideDown();
    } else {
        el.slideUp();
    }
};

$(function(){
	$(".help").toggle("fold",{}, 500);
	$("input[type=checkbox].switch").bootstrapSwitch({ onSwitchChange: function (a, b) { 
			$(this).val(!$(this).val());
			$(this).prop("checked", ! $(this).prop("checked"));
			$(this).trigger('click');
		} 
	});

	$('[data-toggle="popover"]').popover().popover('show')

	$(".bootstrap-switch").each(function(){
		if($(this).find("input").length){
			$inp = $($(this).find("input")[0])
			if($inp.attr('title')) {
				$(this).attr('title', $inp.attr('title')).tooltip()
				$inp.tooltip('destroy')
			} else if($inp.data('original-title')) {
				$(this).attr('title', $inp.data('original-title')).tooltip()
				$inp.tooltip('destroy')
			}
		}
	});
});


$(function(){
	$("[data-toggle-week]").click ( function (e) {
		if( e.target && $(e.target).is("a") ) {
			return true;
		}
		$("#week-summary-" + $(this).data('toggle-week')).toggle()

		e.preventDefault()
	});

	$.nette.init();
});