-- $Id: 100_select_language.lua,v 1.18 2005/08/28 23:36:49 cpressey Exp $

--
-- If gettext isn't enabled, skip this Step - just don't return anything
-- from this scriptlet, and the Flow object will not generate a Step.
--
if not GetText then
	return nil
end

return {
    id = "select_language",
    name = _("Select Language"),
    effect = function(step)
	local actions = {
	    {
                id = "default",
	        name = _("Default (English)"),
	        short_desc = _("Do not apply any language translation"),
		accelerator = "ESC"
	    }
        }

	--
	-- Load list of available languages from configuration file.
	--
	local languages = App.conf.languages

	--
	-- If no languages are available, just skip this step.
	--
	if not languages or table.getn(languages) == 0 then
		return step:next()
	end

	--
	-- Create actions for this dialog box, corresponding to available
	-- languages.
	-- XXX sort languages table by id, first
	-- XXX also create table indexed by id, for lookup in messages?
	--
	local i, lang_tab
	for i, lang_tab in languages do
		table.insert(actions, {
		    id = lang_tab.id,
		    name = lang_tab.name,
		    short_desc = lang_tab.short_desc
		})
	end

	local sel_lang_id = App.ui:present({
	    id = "select_language",
    	    name =  _("Select Language"),
	    short_desc = _("Please select the language you wish you use."),
	    role = "menu",
	    actions = actions
	}).action_id

	if sel_lang_id == "default" then
		App.state.lang_id = nil
	else
		--
		-- Set up appropriate keymap, screenmap, and console fonts.
		--
		if not App.ui:set("lang_syscons", sel_lang_id) then
			App.ui:inform(_(
			    "Unable to apply console settings "	..
			    "for language '%s'.", sel_lang_id
			))
			return step
		end

		--
		-- Set up appropriate environment variables.
		--
		if not App.ui:set("lang_envars", sel_lang_id) then
			App.ui:inform(_(
			    "Unable to set environment variables " ..
			    "for language '%s'.", sel_lang_id
			))
			return step
		end

		if not App.ui:set("lang", sel_lang_id) then
			App.ui:inform(_(
			    "Unable to inform the user interface that " ..
			    "it should now use language '%s'.", sel_lang_id
			))
			return step
		end

		--
		-- Finally, let gettext know about the change of
		-- the selected language:
		--
		GetText.notify_change()

		--
		-- And record it in App.state so that future decisions
		-- can be made based on it:
		--
		App.state.lang_id = sel_lang_id

		--
		-- Record the associated console settings, too.
		--
		for i, lang_tab in languages do
			if lang_tab.id == App.state.lang_id then
				App.state.vidfont = lang_tab.vidfont
				App.state.scrnmap = lang_tab.scrnmap
				App.state.keymap = lang_tab.keymap
				break
			end
		end

	end

	return step:next()
    end
}
