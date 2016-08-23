Param
(
    [switch]$ForceSet
)

$Correct = @{
    'MaxRxRing1Length'  = 4096;
    'MaxRxRing2Length'  = 4096;
    'MaxTxRingLength'   = 4096;
    'NumRxBuffersLarge' = 8192;
    'NumRxBuffersSmall' = 8192
}

Foreach($regKey in Get-ChildItem -Path "Registry::HKLM\SYSTEM\CurrentControlSet\Control\Class\{4D36E972-E325-11CE-BFC1-08002BE10318}" 2> $null)
{
    $Path = "Registry::$($regKey)";
    $Description = (Get-ItemProperty -Path $Path -Name "DriverDesc").DriverDesc
    if($Description -eq "vmxnet3 Ethernet Adapter")
    {
        Write-Host "* Found vmxnet3 adapter"
        Foreach($Key in $Correct.Keys)
        {
            $Value = (Get-ItemProperty -Path $Path -Name $Key).$Key 2> $null
            if($ForceSet)
            {
                Set-ItemProperty -Path $Path -Name $Key -Value $Correct[$Key] -Type string
                Write-Host -ForegroundColor Yellow "MODIFIED $($Key)"
            }
            elseif($Value -eq $Correct[$Key])
            {
                Write-Host -ForegroundColor Green  "OK       $($Key)"
            }
            else
            {
                Write-Host -ForegroundColor Red    "FAIL     $($Key), correcting"
                Set-ItemProperty -Path $Path -Name $Key -Value $Correct[$Key] -Type string
                Write-Host -ForegroundColor Yellow "MODIFIED $($Key)"
            }
        }
    }
}

Write-Host "Completed"
Write-Host "Don't forget to reboot to apply "
Write-Host "Press any key to continue ..."

$x = $host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
