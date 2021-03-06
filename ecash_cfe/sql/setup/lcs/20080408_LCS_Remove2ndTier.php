<?php
/*
	just php <thisfile>
	
	it will check if already applied to the various different runlevels, and apply them
	as needed. I commented out live and ran it, so Local and RC are already done.
*/
class ECash_Update
{	
	protected $db;

	function __construct()
	{
        // Impact
        $this->db['lcs']['local']['user'] = "ecash";
        $this->db['lcs']['local']['pass'] = "lacosanostra";
        $this->db['lcs']['local']['host'] = "monster.tss";
        $this->db['lcs']['local']['port'] = "3309";
        $this->db['lcs']['local']['name'] = "ldb_lcs";

        $this->db['lcs']['local']['obj']  = mysqli_connect(
                $this->db['lcs']['local']['host'],
                $this->db['lcs']['local']['user'],
                $this->db['lcs']['local']['pass'],
                $this->db['lcs']['local']['name'],
                $this->db['lcs']['local']['port']);

        $this->db['lcs']['rc']['user'] = "ecash";
        $this->db['lcs']['rc']['pass'] = "lacosanostra";
        $this->db['lcs']['rc']['host'] = "db101.ept.tss";
        $this->db['lcs']['rc']['port'] = "3308";
        $this->db['lcs']['rc']['name'] = "ldb_lcs";

        $this->db['lcs']['rc']['obj']  = mysqli_connect(
                $this->db['lcs']['rc']['host'],
                $this->db['lcs']['rc']['user'],
                $this->db['lcs']['rc']['pass'],
                $this->db['lcs']['rc']['name'],
                $this->db['lcs']['rc']['port']);

        $this->db['lcs']['live']['user'] = "ecashlcs";
        $this->db['lcs']['live']['pass'] = "hahMeeg4";
        $this->db['lcs']['live']['host'] = "writer.ecashaalm.ept.tss";
        $this->db['lcs']['live']['port'] = "3306";
        $this->db['lcs']['live']['name'] = "ldb_lcs";

        $this->db['lcs']['live']['obj']  = mysqli_connect(
                $this->db['lcs']['live']['host'],
                $this->db['lcs']['live']['user'],
                $this->db['lcs']['live']['pass'],
                $this->db['lcs']['live']['name'],
                $this->db['lcs']['live']['port']);

	}

	function checkConnections($return_on_failure = FALSE)
	{
		echo "\nChecking Connections\n\n";

		foreach ($this->db as $company => $rc_lvl_array)
		{
			echo "Company: $company\n";

			foreach ($rc_lvl_array as $rc_lvl => $var_array)
			{
				echo "Checking whether {$rc_lvl} DB connection succeeded:";

				if ($this->db[$company][$rc_lvl]['obj'] != FALSE)
					echo "\t\t\t[Connected]\n";
				else
				{
					echo "\t\t\t[Failed]\n";

					if ($return_on_error)
						return FALSE;
				}
			}
		}

		return TRUE;
	}

	function checkApplied($return_if_applied = FALSE)
	{
		echo "\nChecking if updates have already been applied\n\n";

		foreach ($this->db as $company => $rc_lvl_array)
		{
			echo "Company: $company\n";

			foreach ($rc_lvl_array as $rc_lvl => $var_array)
			{
				echo "Checking whether {$rc_lvl} DB has been updated:";

				$mysqli = $var_array['obj'];

//////////////////////////////////////////////////////////////////////////////////////////////////
				$query = "SELECT * FROM section WHERE name='external' AND active_status='inactive'";

				$res = $mysqli->query($query);

				if ($res->num_rows == 1)
				{
					echo "\t\t\t[Already Done]\n";
					$this->db[$company][$rc_lvl]['applied'] = true;

					if ($return_if_applied)
						return FALSE;
				}
				else
				{
					echo "\t\t\t[Not Yet]\n";
					$this->db[$company][$rc_lvl]['applied'] = false;
				}
//////////////////////////////////////////////////////////////////////////////////////////////////
			}
		}

		return TRUE;
	}

	function applyChanges($return_on_error = FALSE)
	{
		echo "\nApplying Updates\n\n";

		foreach ($this->db as $company => $rc_lvl_array)
		{
			echo "Company: $company\n";

			foreach ($rc_lvl_array as $rc_lvl => $var_array)
			{
				if ($this->db[$company][$rc_lvl]['applied'] == true)
					continue; // Skip already applied updates

				echo "Applying update to {$rc_lvl}:";

				$mysqli = $var_array['obj'];

//////////////////////////////////////////////////////////////////////////////////////////////////
				// Populate it with proper data
				$query = "UPDATE section SET active_status='inactive' WHERE name='external'";

				$res = $mysqli->query($query);

				$this->db[$company][$rc_lvl]['applied'] = true;
				
				if ($mysqli->affected_rows != 1)
				{
					$this->db[$company][$rc_lvl]['applied'] = false;
				}
///////////////////////////////////////////////////////////////////////////////////////////////////
				if ($this->db[$company][$rc_lvl]['applied'] == false)
				{
					echo "\t\t\t[Failed]\n";

					if ($return_on_error)
					{
						echo mysqli_error($mysqli);
						return FALSE;
					}
				}
				else
				{
					$this->db[$company][$rc_lvl]['applied'] = true;
					echo "\t\t\t[Success]\n";
				}

			}
		}

		return TRUE;

	}


	function rollbackChanges($return_on_error = FALSE)
	{
		echo "\nRolling Back Updates\n\n";

		foreach ($this->db as $company => $rc_lvl_array)
		{
			echo "Company: $company\n";

			foreach ($rc_lvl_array as $rc_lvl => $var_array)
			{
				if ($var_array['applied'] != true)
					continue;

				$mysqli = $var_array['obj'];

				echo "Rolling back update to {$rc_lvl}:";

//////////////////////////////////////////////////////////////////////////////////////////////////
				// Just make it so the check applied fails
				$query = "UPDATE section SET active_status='active' WHERE name='external'";

				$res = $mysqli->query($query);
//////////////////////////////////////////////////////////////////////////////////////////////////

				if ($mysqli->affected_rows < 1)
				{
					echo "\t\t\t[Failed]\n";	
					break;
				}
				else
				{
					echo "\t\t\t[Succeeded]\n";
				}


			}
		}
	}

}

$update = new ECash_Update();

$update->checkConnections(TRUE);
$update->checkApplied(FALSE);
$update->applyChanges(TRUE);
//$update->rollbackChanges();
?>
