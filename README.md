# Spoke Opt-Outs Sync
This PHP script syncs opt-outs from Spoke to EveryAction. We recommend setting up a cron job to run this script regularly, or else you will manually have to run it each time you want to sync. Tested working with [Spoke v10.0](https://github.com/moveonorg/spoke)

## Using the sync
- Configure settings at the top of [sync.php](sync.php)
  - EveryAction API username and password
  - Spoke database credentials
- Change the time period of Spoke opt-outs that are searched for (default syncs rolling past 7 days)
  - To change this, change `7 days` in [line 31](sync.php#L31) to time period you want
