@echo off
title Bonhagan Transit Web Server
echo ================================================================
echo   BONHAGAN TRANSIT - Unified Web & Backend Server
echo ================================================================
echo.
echo [1/2] Starting PHP Development Server with Clean URLs...
echo       Address: http://localhost:8000
echo       Clean URLs: Active (No /frontend, no .html)
echo       Database: Neon PostgreSQL Connected
echo.
echo [2/2] Opening application in your default web browser...
start http://localhost:8000
echo.
echo Server is actively running. Press Ctrl+C to stop.
echo ================================================================
php -S localhost:8000 router.php
pause

