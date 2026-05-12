/**
 * Elementor editor: fill portable targeting JSON from quick-insert buttons.
 */
(function ($) {
	'use strict';

	function readMode($root) {
		var $m = $root.find('.elementor-control-rwgc_geo_mode select');
		if ($m.length) {
			return $m.val() || 'show';
		}
		return 'show';
	}

	function buildAudienceJson(audienceId, mode) {
		return JSON.stringify(
			{
				schema_version: 1,
				enabled: true,
				mode: mode || 'show',
				match: 'any',
				rules: [
					{
						id: 'rule_audience',
						label: '',
						match: 'all',
						conditions: [
							{
								type: 'audience',
								operator: 'in',
								value: [String(audienceId)],
							},
						],
					},
				],
			},
			null,
			2
		);
	}

	function buildCampaignJson(campaignToken, mode) {
		return JSON.stringify(
			{
				schema_version: 1,
				enabled: true,
				mode: mode || 'show',
				match: 'any',
				rules: [
					{
						id: 'rule_campaign',
						label: '',
						match: 'all',
						conditions: [
							{
								type: 'campaign',
								operator: 'in',
								value: [String(campaignToken)],
							},
						],
					},
				],
			},
			null,
			2
		);
	}

	function fillPortableTextarea(json) {
		var $ta = $('#elementor-panel-inner').find('.elementor-control-rwgc_portable_geo_targeting textarea');
		if (!$ta.length) {
			return;
		}
		$ta.val(json);
		$ta.trigger('input');
		$ta.trigger('change');
	}

	$(function () {
		$(document).on('click', '.rwgc-el-insert-audience', function (e) {
			e.preventDefault();
			var id = $(this).data('audience-id');
			if (!id) {
				return;
			}
			var mode = readMode($('#elementor-panel-inner'));
			fillPortableTextarea(buildAudienceJson(id, mode));
		});

		$(document).on('click', '.rwgc-el-insert-campaign', function (e) {
			e.preventDefault();
			var c = $(this).data('campaign');
			if (!c) {
				return;
			}
			var mode = readMode($('#elementor-panel-inner'));
			fillPortableTextarea(buildCampaignJson(c, mode));
		});
	});
})(jQuery);
