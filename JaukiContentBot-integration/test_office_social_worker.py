import unittest
from unittest.mock import patch, MagicMock

import office_social_worker


class TestOfficeSocialWorker(unittest.TestCase):
    @patch('office_social_worker.office_bridge')
    def test_worker_process_command_feed(self, mock_bridge):
        client = MagicMock()
        command = {
            "id": "123",
            "agent_id": "jauki-social",
            "action": "generate_feed"
        }
        
        with patch('office_social_worker._do_generate') as mock_generate:
            result = office_social_worker.process_command(command, client, idle_delay=0)
            
            self.assertTrue(result)
            client.update.assert_any_call("123", "running")
            client.update.assert_any_call("123", "completed")
            mock_generate.assert_called_once()
            
    @patch('office_social_worker.office_bridge')
    def test_worker_process_command_story(self, mock_bridge):
        client = MagicMock()
        command = {
            "id": "124",
            "agent_id": "jauki-social",
            "action": "generate_story"
        }
        
        with patch('office_social_worker._do_generate') as mock_generate:
            result = office_social_worker.process_command(command, client, idle_delay=0)
            
            self.assertTrue(result)
            client.update.assert_any_call("124", "running")
            client.update.assert_any_call("124", "completed")
            mock_generate.assert_called_once()
            
    @patch('office_social_worker.office_bridge')
    def test_worker_process_invalid_command(self, mock_bridge):
        client = MagicMock()
        command = {
            "id": "125",
            "agent_id": "jauki-social",
            "action": "publish_last"
        }
        
        result = office_social_worker.process_command(command, client, idle_delay=0)
        
        self.assertFalse(result)
        client.update.assert_any_call("125", "running")
        client.update.assert_any_call("125", "failed", "Command 'publish_last' is not allowlisted for the Social worker")

if __name__ == '__main__':
    unittest.main()
