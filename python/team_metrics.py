# Sample script to generate a custom report of team touched/completed metrics
import request, datetime
# The last 30 days.
end_at = datetime.datetime.utcnow()
start_at = end_at - datetime.timedelta( days = 30 )

# Request all teams. Need to `include` the `team` resource, otherwise you will
#  only get the ID.
teams = request( "get", "/api/v1/teams?include=team" )["data"]

#------------------------------------------------------------------------------
for team in teams:
   print( "\n-----------------------------------------" )
   print( f"Stats for {team['title']}" )
   print( "-----------------------------------------" )
   print( "Name                | Touched | Completed" )
   
   # Fetch all members in each team, exclude disabled members.
   members = request( "get", f"/api/v1/teams/{team['id']}/members?is_enabled=true" )["data"]
   for member in members:
      metrics_map = {}
      
      # This endpoint contains cases touched.
      metrics = request( "get", f"/api/v1/insights/cases/metrics", query = {
         "agent_id" : member['id'],
         "start_at" : start_at.isoformat() + "+00:00", # For some reason Kayako
         "end_at"   : end_at.isoformat() + "+00:00" # API is VERY picky about 
                                                # the timestamp format.
      })["data"]
      
      for metric in metrics["metric"]:
         metrics_map[metric["name"]] = metric["value"]
         
      # This contains cases completed.
      metrics = request( "get", f"/api/v1/insights/cases/completed", query = {
         "agent_id" : member['id'],
         "start_at" : start_at.isoformat() + "+00:00",
         "end_at"   : end_at.isoformat() + "+00:00"
      })["data"]
      total_completed = metrics["metric"]["value"]
      
      # Format and print this user.
      print( "%-20s| %-8d| %-9d" % (member["full_name"]
                                  , metrics_map["cases_touched"]
                                  , total_completed) )

      