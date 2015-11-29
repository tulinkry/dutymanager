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

Nette.toggle = function (id, visible) {};

$(function(){
	$(".help").toggle("fold",{}, 500);
	$("input[type=checkbox].switch").bootstrapSwitch({ onSwitchChange: function (a, b) { 
			var data = $(this).data("nette-rules");
			if (data && data[0] && data[0]["toggle"]) {
				var key = Object.keys (data[0]["toggle"]);
				if(!key.length)
					return;
			    if (b) {
			        $('#' + key).slideDown();
			    } else {
			        $('#' + key).slideUp();
			    }
			}
		} 
	});

	$("input[type=checkbox].switch").each(function () { 
		var data = $(this).data("nette-rules");
		if (data && data[0] && data[0]["toggle"]) {
			var key = Object.keys (data[0]["toggle"]);
			if(!key.length)
				return;
			
		    if ($(this).prop("checked")) {
		        $('#' + key).slideDown();
		    } else {
		        $('#' + key).slideUp();
		    }
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



	$.nette.ext({
		init: function() {
			$(".exportForm .index-group").hide();
			$(".exportForm .draggable").sortable(this.options);
		},
		load: function () {
			$(".exportForm .index-group").hide();
			$(".exportForm .draggable").sortable(this.options);
		}
	}, {
		options: {
			start: function (e, ui) {
				ui.item.startIndex = ui.item.index();
			},
			update: function (e, ui) {
				$(this).find(".index").each(function(i){
					$(this).val(i + 1)
				});
			}
		}		
	})

	$.nette.init();
});