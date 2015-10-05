::RemovePrefix.bat  Prefix  fileMask
@echo off
setlocal
for %%A in ("Prefix*.*") do (
    set "fname=%%~A"
    call ren "%%fname%%" "%%fname:*Prefix=%%"
)
endlocal