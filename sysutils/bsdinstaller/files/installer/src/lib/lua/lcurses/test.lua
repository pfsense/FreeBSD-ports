--require('bit')
local curses = require("curses")

local _topw = {}
local top_lines = 10
curses.slk_init(2)

local rip =    function(w, columns)
        table.insert(_topw, w)
        w:clear()
        w:mvaddstr(0,0,'hello world '..table.getn(_topw))
        w:noutrefresh()
    end
curses.ripoffline(true, rip)
curses.ripoffline(false, rip)
curses.ripoffline(false, rip)
curses.ripoffline(false, rip)
--curses.ripoffline(true, rip)
local main = curses.init()
if not main then return; end

local function _main()
    assert(_topw)

    curses.start_color()
    assert(curses.nl(false))
    assert(main == curses.main_window())

    -- labels
    curses.slk_set(1, 'lbl-1', 0)
    curses.slk_noutrefresh()
    curses.slk_attron(curses.A_BLINK)
    curses.slk_set(5, '<', 0)
    curses.slk_set(6, '-', 1)
    curses.slk_set(7, '-', 1)
    curses.slk_set(8, '>', 2)
    curses.slk_touch()
    curses.slk_noutrefresh()

    -- top window with terminal attributes
    local top = curses.new_window(top_lines, curses.columns(), 0, 0)
    local p_top = curses.new_panel(top)
    top:leaveok()
    top:border()
    top:mvaddstr(1, 1, 'Terminal: '..curses.termname()..' - '..curses.longname())
    top:mvaddstr(2, 1, 'Baudrate: '..curses.baudrate())
    top:mvaddstr(3, 1, 'EraseCh : '..curses.erase_char())
    top:mvaddstr(4, 1, 'KillCh  : '..curses.kill_char())
    top:mvaddstr(5, 1, 'has ic  : '..tostring(curses.has_insert_char()))
    top:mvaddstr(6, 1, 'has il  : '..tostring(curses.has_insert_line()))
    top:mvaddstr(7, 1, 'TermAttr: '..string.format("%x", curses.termattrs())..' can blink: '..tostring(curses.termattrs(curses.A_BLINK)))
    top:mvaddstr(8, 1, 'HasColor: ')
    if (curses.has_colors()) then
        curses.init_pair(1, curses.COLOR_GREEN, curses.COLOR_BLACK)
        curses.init_pair(2, curses.COLOR_MAGENTA, curses.COLOR_BLACK)
        curses.init_pair(3, curses.COLOR_CYAN, curses.COLOR_BLACK)
        top:attrset(curses.color_pair(1) + curses.A_BLINK)
        top:addstr('y')
        top:attrset(curses.color_pair(2))
        top:addstr('e')
        top:attrset(curses.color_pair(3) + curses.A_BOLD)
        top:addstr('s')
        top:standend()
        top:addstr(' '..curses.colors()..' '..curses.color_pairs())
    else
        top:addstr('no')
    end

    local win = curses.new_window(curses.lines() - top_lines, curses.columns(), top_lines, 0)
    local p_win = curses.new_panel(win)
    win:keypad(true)
    win:nodelay(true)

    win:border()

    win:mvhline(10, 1, curses.ACS_HLINE, curses.columns() - 2)

    curses.update_panels()
    curses.doupdate()

    ---[[
    curses.echo(false)
    win:nodelay(true)
    repeat
        c = win:getch()
        curses.napms(50)
    until (c)
    if c == 27 then
        win:nodelay(true)
        c1 = win:getch()
        c2 = win:getch()
        c3 = win:getch()
        c4 = win:getch()
    end
    --]]

    --[[
    win:nodelay(false)
    curses.echo()

    c = win:getstr()
    --]]

    --[[
    for i = 1, 10 do
        curses.flash()
        curses.doupdate()
        curses.napms(100)
    end
    --]]

    -- delete created panels
    p_top:close()
    p_win:close()
    -- delete created windows
    top:close()
    win:close()
    -- clear the screen for terminals that don't restore the screen
    main:clear()
    main:noutrefresh()
    curses.slk_clear()
    curses.slk_noutrefresh()
    curses.doupdate()
end

local ok, msg = xpcall(_main, _TRACEBACK)

curses.done()

if not ok then
    print(msg)
else
    --[[
    if (c) then print(c) end
    if (c) then print(c, curses.keyname(c)) end
    --]]
    ---[[
    print()
    print(c, c1, c2, c3, c4)
    --]]
end
